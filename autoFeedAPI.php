<?php
ini_set("display_errors", 0);

//include app
require("../inc/app.php");
require("HelperClasses/Collection.php");
require("HelperClasses/PennysaverCarFeed.php");
require("HelperClasses/VehicleArrayBuilder.php");

use HelperClasses\Collection;
use HelperClasses\SiteCarFeed;
use HelperClasses\VehicleArrayBuilder;

$auto_listings = $store->db->automotive->all();

$rawFeed = [];
while ($vehicle = $auto_listings->fetch_assoc()) {
    $rawFeed[] = [
        "id" => $vehicle['ad_id'],
        "copy" => htmlentities($vehicle['ad_copy']),
        "photo" => $vehicle['photo'],
        "source" => "http://www.pennysaveronline.com/classified/ads/automotive/cars/#{$vehicle['ad_id']}"
    ];
}

if (isset($_GET['raw']))
    die(var_dump($rawFeed));


$feed = Collection::make($rawFeed)
    ->map(function ($vehicle) {

        $auto = new PennysaverCarFeed($vehicle);

        try {
            list($year, $make, $model) = $auto->getYearMakeModel();

            return [
                "year" => $year,
                "make" => $make,
                "model" => $model,
                "miles" => $auto->getMiles(),
                "price" => $auto->getPrice(),
                "ad_id" => $vehicle['id'],
                "copy" => $vehicle['copy'],
                "photo" => $vehicle['photo'],
                "source" => $vehicle['source']
            ];
        } catch (\Exception $e) {
            return ['Error' => $e->getMessage()];
        }

    });

$errors = $feed->filter(function ($vehicle) {
    if (isset($vehicle['Error']))
        return true;
})->toArray();

$vehicles = $feed->filter(function ($vehicle) {
    if (!isset($vehicle['Error']))
        return true;
})->toArray();


header("Content-Type: application/json; charset=utf-8");

echo json_encode(["raw_total" => count($rawFeed),
    "errors_count" => count($errors),
    "data_count" => count($vehicles),
    "errors" => array_values($errors),
    "data" => array_values($vehicles)
]);


