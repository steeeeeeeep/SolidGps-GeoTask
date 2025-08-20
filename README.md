# SolidGps-GeoTask
A Geo data mapping Trial Task

This project is a task to calculate geodata utilizing php tech stack.

- Clean: discard rows with invalid coordinates or bad timestamps ? log to rejects.log.
- Order: sort remaining points by timestamp.
- Split: create a new trip when:
- time gap > 25 minutes **or**
- straight-line distance jump > 2 km (use haversine formula).
- Number trips sequentially (trip_1, trip_2, …).
- For each trip, compute:
- total distance (km), duration (min), average speed (km/h), and max speed (km/h)
- Output GeoJSON:
- FeatureCollection with each trip as a LineString (GeoJSON spec), each coloured differently
