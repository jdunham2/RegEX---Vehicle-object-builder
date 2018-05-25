<?php
namespace HelperClasses;


class PennysaverCarFeed
{
    private $vehicle;
    private $description;
    private $year_pattern;
    private $make_pattern;
    private $model_pattern;
    private $real_makes;
    private $make;

    public function __construct(Array $vehicle)
    {
        $this->vehicle = $vehicle;
        $this->description = htmlentities($vehicle["copy"]);

        $this->real_makes = VehicleArrayBuilder::makes()->map(function($make) {
            return strtolower($make);
        })->toArray();
    }

    public function getYearMakeModel()
    {
        //require being called in this order
        return [$this->getYear(), $this->getMake(), $this->getModel()];
    }

    protected function getYear()
    {
        $two_digit = "\s0[1-9]";
        $nineties = "^19[2-9][0-9]|[\s]19[2-9][0-9]";
        $two_thousands = "^20[01][0-9]|[\s]20[01][0-9]";

        if (!preg_match("/[^-\$]?({$two_digit}|{$nineties}|{$two_thousands})\s/", $this->description, $match))
            $this->error();

        $this->year_pattern = "(?:{$match[1]})";

        return $this->formatted($match[1], "year");
    }


    protected function getMake()
    {
        if (!preg_match("/{$this->year_pattern} ([a-zA-Z0-9]+)\s/", $this->description, $match))
            $this->error();

        $this->make = $this->fullnameFromAbbreviation($match[1]);

        $this->checkFakeMake($this->make);

        $this->make_pattern = "(?:{$match[1]})";

        return $this->formatted($this->make);
    }


    protected function getModel()
    {
        $model_pattern = "([a-zA-Z0-9-]+)[,.]?";
        $year_make_pattern = "{$this->year_pattern}\s{$this->make_pattern}\s";

        if (!preg_match("/{$year_make_pattern}{$model_pattern}\s/", $this->description, $match))
            $this->error();

        $model = $this->modelContinued($match[1]);

        $this->checkFakeModel($this->make, $model);

        $this->model_pattern = "(?:{$model})";

        return $this->formatted($model);
    }


    public function getMiles()
    {
        preg_match("/([0-9,]+)(?:K|k)?(?: original)?(?: miles|K|k)/", $this->description, $match);

        return $this->formatted(isset($match[1]) ? $match[1] : null, 'miles');
    }


    public function getPrice()
    {
        preg_match("/[\$]([0-9,]+)/", $this->description, $match);

        return $this->formatted(isset($match[1]) ? $match[1] : null, 'price');
    }

    private function formatted($value, $type = "words")
    {
        if (is_null($value))
            return $value;

        $value = $this->reduce($value);

        if (is_array($value)) {

            return array_map(function ($val) use ($type) {
                return $this->formatted($val, $type);
            }, $value);
        }

        $formatType = "format" . ucfirst(strtolower($type));
        return $this->$formatType($value);
    }

    private function formatWords($value)
    {
        return ucwords(strtolower($value));
    }

    private function formatYear($year)
    {
        $year = trim($year, " ");

        if (strlen($year) == 2) {
            return "20{$year}";
        }

        return $year;
    }

    private function formatMiles($miles)
    {
        $miles = $this->Kto000($miles);
        return $this->removeCommas($miles);
    }

    private function formatPrice($price)
    {
        return $this->removeCommas($price);
    }

    private function reduce($input)
    {
        if (!is_array($input)) {
            return $input;
        }

        //return just first item if array length is 1
        if (count($input) == 1) {
            return $input[0];
        }

        return $input;
    }


    private function fullnameFromAbbreviation($make)
    {
        $make = strtolower($make);

        $know_abbreviations = [
            "chevy" => "CHEVROLET",
            "olds" => "oldsmobile",
            "mercedes" => "Mercedes-Benz"
        ];

        return isset($know_abbreviations[$make]) ? $know_abbreviations[$make] : $make;
    }

    private function modelContinued($model)
    {
        $know_parts_of_model_name = [
            "ram",
            "grand",
            "monte",
            "sierra",
            "silverado",
            "town",
        ];

        if (in_array(strtolower($model), $know_parts_of_model_name)) {
            preg_match("/{$this->year_pattern}\s{$this->make_pattern}\s{$model}\s([a-zA-Z0-9]+)[,]?\s/", $this->description, $match);
            $model .= isset($match[1]) ? " " . $match[1] : "";
        }

        return $model;
    }

    private function Kto000($val)
    {
        if (in_array(strlen($val), [2, 3])) {
            $val .= "000";
        }

        return $val;
    }

    private function removeCommas($val)
    {
        return preg_replace("/,/", '', $val);
    }

    private function error()
    {
        throw new \Exception('Unable to find vehicle in ad copy: ' . $this->description);
    }

    private function checkFakeMake($make)
    {
        if (!in_array(strtolower($make), $this->real_makes))
            $this->error();
    }

    private function checkFakeModel($make, $model)
    {
        if (!VehicleArrayBuilder::modelExists($make, $model))
            $this->error();
    }

}