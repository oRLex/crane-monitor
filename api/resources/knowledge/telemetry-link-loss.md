# Telemetry link loss (offline alarm)

> Demo library document.

## Meaning

The crane controller or the site gateway stopped sending data. CraneWatch raises an **offline (critical)** alarm when no valid reading arrives. Offline does not mean the crane stopped — it means we are blind.

## Typical causes

- Power to the gateway cabinet was switched off (often at the end of a shift or during electrical works on site).
- Mobile network (LTE) coverage dropped; common in early construction phases or behind new concrete walls.
- The crane was switched off at the main isolator.
- Controller fault or a loose cable on the anemometer/load cell bus.

## Procedure

1. Call the operator or the site to confirm whether the crane is in operation.
2. If the crane **is** operating while offline, the remote monitoring of wind and overload is not available. The site must rely on the on-board limiter only; inform the site manager.
3. If the link is not back within 30 minutes during working hours, create a service ticket for the gateway.
4. Short drop-outs (under 1 minute) that recover by themselves need no action, but if there are more than 5 per day, check the LTE antenna and signal strength.
