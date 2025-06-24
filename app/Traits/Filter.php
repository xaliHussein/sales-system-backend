<?php

namespace App\Traits;


trait Filter
{
    public function filter($data, $object)
    {
        // $data => this data filtred
        // $object => {"name":"" , "value":""}
        $filter = json_decode($object);
        $data->where($filter->name, $filter->value);
        return $data;
    }
}
