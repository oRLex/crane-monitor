<?php

namespace App\Telemetry\Bus;

use App\Telemetry\TelemetryPoint;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Topic exchange `telemetry`, routing key `crane.{id}` — the shape an MQTT bridge / IoT hub
 * would publish with. One durable queue feeds the processor; more consumers (archival,
 * analytics) can bind their own queues without touching the producer.
 */
class RabbitMqBus implements TelemetryBus
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    /** @var list<TelemetryPoint> */
    private array $buffer = [];

    private ?AMQPMessage $lastMessage = null;

    public function __construct(
        private readonly string $url,
        private readonly string $exchange,
        private readonly string $queue,
    ) {}

    public function publish(array $points): void
    {
        $channel = $this->channel();
        foreach ($points as $p) {
            $message = new AMQPMessage(json_encode($p->toArray(), JSON_THROW_ON_ERROR), [
                'content_type' => 'application/json',
                // Live telemetry is perishable: drop it if nobody consumed it within 30s.
                'expiration' => '30000',
            ]);
            $channel->batch_basic_publish($message, $this->exchange, "crane.{$p->craneId}");
        }
        $channel->publish_batch();
    }

    public function consume(Closure $handler): void
    {
        $channel = $this->channel();
        $channel->queue_declare($this->queue, false, true, false, false);
        $channel->queue_bind($this->queue, $this->exchange, 'crane.*');
        $channel->basic_qos(0, 200, false);

        // Batch per tick: messages carrying the same timestamp are processed together.
        $channel->basic_consume($this->queue, '', false, false, false, false, function (AMQPMessage $m) use ($handler) {
            $point = TelemetryPoint::fromArray(json_decode($m->getBody(), true, flags: JSON_THROW_ON_ERROR));
            if ($this->buffer !== [] && $this->buffer[0]->ts !== $point->ts) {
                $this->flush($handler);
            }
            $this->buffer[] = $point;
            $this->lastMessage = $m;
        });

        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, 0.25);
            } catch (AMQPTimeoutException) {
                $this->flush($handler); // quiet period: don't hold a partial batch
            }
        }
    }

    /** @param Closure(list<TelemetryPoint>): void $handler */
    private function flush(Closure $handler): void
    {
        $last = $this->lastMessage;
        if ($this->buffer === [] || $last === null) {
            return;
        }
        $handler($this->buffer);
        $last->ack(true); // ack everything up to the last message in one go
        $this->buffer = [];
        $this->lastMessage = null;
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }
        $parts = parse_url($this->url) ?: [];
        $config = new AMQPConnectionConfig;
        $config->setHost($parts['host'] ?? '127.0.0.1');
        $config->setPort($parts['port'] ?? (($parts['scheme'] ?? '') === 'amqps' ? 5671 : 5672));
        $config->setUser(urldecode($parts['user'] ?? 'guest'));
        $config->setPassword(urldecode($parts['pass'] ?? 'guest'));
        $config->setVhost(urldecode(ltrim($parts['path'] ?? '/', '/')) ?: '/');
        if (($parts['scheme'] ?? '') === 'amqps') {
            $config->setIsSecure(true);
        }
        $this->connection = AMQPConnectionFactory::create($config);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchange, AMQPExchangeType::TOPIC, false, true, false);

        return $this->channel;
    }
}
