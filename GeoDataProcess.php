<?php
require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('GIS-Rejects');

$log->pushHandler(new StreamHandler(__DIR__ . '/reject.log', Logger::WARNING));

$fileName = __DIR__ . "/data/points.csv";
$handleFile = file_get_contents($fileName);
$pointsList = array_map("str_getcsv", explode("\n", trim($handleFile)));

$points = [];

foreach ($pointsList as $data) {

  if (count($data) > 3 && isValidCoordinates($data[1], $data[2]) && isValidTimeFormat($data[3])) {
    $points[] = [
      "lat" => $data[1],
      "lon" => $data[2],
      "time" => $data[3],
    ];
  } else {
    // $logMessage = "Invalid Coordinates";
    // $log->warning("Points {" . implode($data) . "} has been rejected.", [
    //   "Reason" => $logMessage
    // ]);
  }

}

array_multisort(array_column($points, 'time'), SORT_ASC, $points);

$trips = [];
$currTrip = [];
$tripIndex = 1;

for ($i = 0; $i < count($points); $i++) {

  if ($i === 0) {
    $currTrip[] = $points[$i];
    continue;
  }

  $currPoints = $points[$i];
  $prevPoints = $points[$i - 1];

  $distanceJump = calc_haversine($prevPoints['lat'], $prevPoints['lon'], $currPoints['lat'], $currPoints['lon']);
  $timeDiff = (strtotime($currPoints['time']) - strtotime($prevPoints['time'])) / 60;
  // echo "Time diff: $timeDiff min, Distance: $distanceJump km\n";
  // $log->warning("Points has been rejected.", [
  //   "Reason" => "Time diff: $timeDiff min, Distance: $distanceJump km\n"
  // ]);
  if ($timeDiff > 25 || $distanceJump > 2) {
    echo $timeDiff . "\n" . $distanceJump . "'n";
    if (!empty($currTrip)) {
      $trips["trip_" . $tripIndex] = tripSummary($currTrip);
      $tripIndex++;
      $currTrip = [];
    }
  }

  $currTrip = $currPoints;
}

if (!empty($currTrip)) {
  $trips["trip_" . $tripIndex] = tripSummary($currTrip);
}

print_r($trips);


function tripSummary($trip)
{
  $totalDistance = 0;
  $maxSpeed = 0;
  $duration = 0;
  $avgSpeed = 0;
  $dist = 0;
  $timeDiffHrs = 0;

  for ($i = 1; $i < count($trip); $i++) {
    if (!isset($trip[$i - 1]) || !isset($trip[$i])) {
      continue;
    }

    $prev = $trip[$i - 1];
    $curr = $trip[$i];
    if (
      is_array($prev) && isset($prev['lat'], $prev['lon']) &&
      is_array($curr) && isset($curr['lat'], $curr['lon'])
    ) {
      $dist = calc_haversine($prev['lat'], $prev['lon'], $curr['lat'], $curr['lon']);
      $timeDiffHrs = max(0.0001, (strtotime($curr['time']) - strtotime($prev['time'])) / 3600);
    }


    if ($dist != 0 && $timeDiffHrs !== 0) {
      $speed = $dist / $timeDiffHrs;
      if ($speed > $maxSpeed)
        $maxSpeed = $speed;
    }

    $totalDistance += $dist;
  }

  if (isset(($trip[count($trip) - 1]['time'])) && strtotime($trip[0]['time'])) {
    $duration = (strtotime($trip[count($trip) - 1]['time']) - strtotime($trip[0]['time'])) / 60;
    $avgSpeed = ($duration > 0) ? ($totalDistance / ($duration / 60)) : 0;
  }

  return [
    'total_distance_km' => round($totalDistance, 2),
    'duration_min' => round($duration, 1),
    'avg_speed_kmh' => round($avgSpeed, 2),
    'max_speed_kmh' => round($maxSpeed, 2),
    'points' => $trip
  ];
}

function calc_haversine($lat1, $lon1, $lat2, $lon2)
{
  $R = 6371;

  $dlat = deg2rad($lat2 - $lat1);
  $dlon = deg2rad($lon2 - $lon1);

  $a = sin($dlat / 2) * sin($dlat / 2) +
    cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
    (sin($dlon / 2) * sin($dlon / 2));

  $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

  return $R * $c;
}

function isValidCoordinates($lat, $lon)
{
  return is_numeric($lat) && is_numeric($lon) &&
    $lat >= -90 && $lat <= 90 &&
    $lon >= -180 && $lon <= 180;
}

function isValidTimeFormat($timeStamp)
{
  return (bool) strtotime($timeStamp);
}