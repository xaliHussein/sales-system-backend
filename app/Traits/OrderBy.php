<?php

namespace App\Traits;


trait OrderBy
{
    public function order_by($data, $key)
    {
        foreach ($_GET as $key => $value) {
            if ($key == 'skip' || $key == 'limit' || $key == 'query' || $key == 'filter') {
                continue;
            } else {
                $sort = $value == 'true' ? 'desc' : 'asc';
                $data->orderBy($key,  $sort);
            }
        }
        return $data;
    }
}
