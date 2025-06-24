<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

trait Search
{
    public function search($data, $table)
    {
        $data->where(function ($q) use ($table) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', '%' . $_GET['query'] . '%');
            }
        });
        return $data;
    }
}
