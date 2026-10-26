# Load moment limiter and overload procedure

> Demo library document.

## What load moment means

Load moment is the load multiplied by its radius (tonne-metres). Every tower crane has a **rated load moment**; the load chart is derived from it. A heavy load is allowed close to the mast, but the same load at the jib tip may exceed the rating.

Load moment % = (load in t × radius in m) / rated moment (t·m) × 100.

Example: a Flat-top 172 has a rated moment of 172 t·m. Lifting 3.2 t at 50 m gives 160 t·m = 93 % — a warning.

## Thresholds

- **90 % — overload warning.** The limiter slows trolley-out and hoist-up speeds. The operator should not move the load further out.
- **100 % — overload trip (critical).** Hoist-up and trolley-out are blocked. Only movements that reduce the moment (trolley-in, lowering) are possible.

## What to do on an overload alarm

1. Do not try to "bounce" the load through the trip or bypass the limiter. Bypassing is prohibited.
2. Reduce the radius (trolley in) or lower the load to the ground.
3. Check the actual weight of the load against the delivery note — overloads are most often caused by an underestimated weight (wet timber, concrete left in a skip, bundled rebar).
4. Re-plan the lift: split the load, or lift from a smaller radius.
5. Report every critical overload to the site manager. Three or more critical overloads on one crane in a shift should trigger a review of lift planning.

## Repeated warnings

Many overload *warnings* without trips usually mean the crane is routinely working at the edge of its chart. That is allowed but leaves no margin for wind or dynamic effects. Recommend lifting closer to the mast or using a crane with a larger rated moment.
