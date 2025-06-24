<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\Orders;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

trait YearStatistics
{
    public $firstDayOfFirstMonth;
    public $firstMonthWithFirstDay;
    public $lastDayOfLastMonth;
    public $lastMonthWithLastDay;
    public $branch;
    public $user_type;
    public $type_study;

    public function __construct()
    {
        $this->firstDayOfFirstMonth = Carbon::now()->startOfYear();
        $this->firstMonthWithFirstDay = $this->firstDayOfFirstMonth->format('Y-m-d');
        $this->lastDayOfLastMonth = Carbon::now()->endOfYear();
        $this->lastMonthWithLastDay = $this->lastDayOfLastMonth->format('Y-m-d');
        $this->branch = auth()->user()->branch;
        $this->user_type = auth()->user()->user_type;
        $this->type_study = auth()->user()->type_study;
    }

    public function yearOrdersCount()
    {
        if ($this->user_type == 0) {
            $orders_count = Orders::where('order_status', '!=', 0)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->count();
        } elseif ($this->user_type == 3) {
            $orders_count = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->count();
        } else {
            $orders_count = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->where('type_study', $this->type_study)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->count();
        }

        return  $orders_count;
    }
    public function yearOrdersSum()
    {
        if ($this->user_type == 0) {
            $orders_sum = Orders::where('order_status', '!=', 0)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->sum('total');

        } elseif ($this->user_type == 3) {
            $orders_sum = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->sum('total');
        } else {
            $orders_sum = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->where('type_study', $this->type_study)
            ->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
            ->sum('total');
        }
        return $orders_sum;


    }
    public function yearOrdersCountStatistics()
    {
        if ($this->user_type == 0) {
            $orders_year = Orders::where('order_status', '!=', 0)
            ->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('COUNT(*) AS count')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();

        }
         elseif ($this->user_type == 1) {
            $orders_year = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->where('type_study', $this->type_study)
            ->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('COUNT(*) AS count')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();


        } else {
            $orders_year = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('COUNT(*) AS count')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();
        }


        $salesChartByMonth = [];
        $oneYears = CarbonPeriod::create($this->firstMonthWithFirstDay, '1 month', $this->lastMonthWithLastDay);

        foreach ($oneYears as $date) {
            $dateString = $date->format('F');
            $salesChartByMonth[$dateString] = 0;

        }

        foreach ($orders_year as $data) {
            $date = date('F', strtotime($data['date']));
            if (isset($salesChartByMonth[$dateString])) {
                $salesChartByMonth[$date] += $data['count'];
            }
        }

        $data = [];
        $chart = [];
        foreach ($salesChartByMonth as $key => $val) {
            array_push($chart, $key);
            array_push($data, $val);
        }
        return  $resulte_one_year = [$data, $chart];
    }
    public function yearOrdersSumStatistics()
    {
        if ($this->user_type == 0) {
            $orders_year = Orders::where('order_status', '!=', 0)->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('SUM(total) AS total')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();
        } elseif ($this->user_type == 1) {
            $orders_year = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->where('type_study', $this->type_study)
            ->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('SUM(total) AS total')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();
        } else {
            $orders_year = Orders::where('order_status', '!=', 0)
            ->where('branch', $this->branch)
            ->select([
                DB::raw('DATE(created_at) AS date'),
                DB::raw('SUM(total) AS total')
            ])->whereBetween('created_at', [$this->firstDayOfFirstMonth, $this->lastMonthWithLastDay])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->toArray();


        }

        $salesChartByMonth = [];
        $oneYears = CarbonPeriod::create($this->firstMonthWithFirstDay, '1 month', $this->lastMonthWithLastDay);

        foreach ($oneYears as $date) {
            $dateString = $date->format('F');
            $salesChartByMonth[$dateString] = 0;

        }

        foreach ($orders_year as $data) {
            $date = date('F', strtotime($data['date']));
            if (isset($salesChartByMonth[$dateString])) {
                $salesChartByMonth[$date] += $data['total'];
            }
        }

        $data = [];
        $chart = [];
        foreach ($salesChartByMonth as $key => $val) {
            array_push($chart, $key);
            array_push($data, $val);
        }
        return  $resulte_one_year = [$data, $chart];
    }
}
