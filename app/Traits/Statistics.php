<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\Sales;
use App\Models\Dozens;
use App\Models\Product;
use Carbon\CarbonPeriod;
use App\Models\Sale_items;
use Illuminate\Support\Facades\DB;

trait Statistics
{
    /** @var Carbon Start of statistics year in Baghdad (Jan 1, 03:00). */
    public $yearQueryStart;

    /** @var Carbon Exclusive end of statistics year in Baghdad (next Jan 1, 03:00). */
    public $yearQueryEndExclusive;

    public $firstMonthWithFirstDay;

    public $lastMonthWithLastDay;

    public function __construct()
    {
        $year = (int) Carbon::now('Asia/Baghdad')->format('Y');
        $this->yearQueryStart = Carbon::create($year, 1, 1, 3, 0, 0, 'Asia/Baghdad');
        $this->yearQueryEndExclusive = Carbon::create($year + 1, 1, 1, 3, 0, 0, 'Asia/Baghdad');
        $this->firstMonthWithFirstDay = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Baghdad')->format('Y-m-d');
        $this->lastMonthWithLastDay = Carbon::create($year, 12, 1, 0, 0, 0, 'Asia/Baghdad')->format('Y-m-d');
    }

    /**
     * Store “business calendar date” in Asia/Baghdad when timestamps are stored in UTC (Iraq +3, no DST).
     * A business day runs [D 03:00, D+1 03:00) Baghdad.
     */
    private function sqlBusinessDateExpr(string $qualifiedColumn): string
    {
        return "DATE(DATE_SUB(DATE_ADD({$qualifiedColumn}, INTERVAL 3 HOUR), INTERVAL 3 HOUR))";
    }

    /** Start of the business day containing $moment (Baghdad), i.e. D at 03:00. */
    private function businessDayStartBaghdad(?Carbon $momentBaghdad = null): Carbon
    {
        $m = ($momentBaghdad ?? Carbon::now('Asia/Baghdad'))->copy()->timezone('Asia/Baghdad');
        $cal = $m->copy()->startOfDay();
        if ($m->lt($cal->copy()->addHours(3))) {
            $cal->subDay();
        }

        return $cal->copy()->addHours(3);
    }

    private function businessDayEndExclusiveBaghdad(Carbon $businessDayStartBaghdad): Carbon
    {
        return $businessDayStartBaghdad->copy()->addDay();
    }

    /** Sunday 03:00 → next Sunday 03:00 (exclusive), Baghdad. */
    private function weeklyBusinessWindowBaghdad(): array
    {
        $now = Carbon::now('Asia/Baghdad');
        $weekStart = $now->copy()->startOfWeek(Carbon::SUNDAY)->setTime(3, 0, 0);
        if ($now->lt($weekStart)) {
            $weekStart->subWeek();
        }
        $weekEndExclusive = $weekStart->copy()->addWeek();

        return [$weekStart, $weekEndExclusive];
    }

    public function year_sales_sum_statistics()
    {
        $months_ar = [
            'January' => 'يناير',
            'February' => 'فبراير',
            'March' => 'مارس',
            'April' => 'أبريل',
            'May' => 'مايو',
            'June' => 'يونيو',
            'July' => 'يوليو',
            'August' => 'أغسطس',
            'September' => 'سبتمبر',
            'October' => 'أكتوبر',
            'November' => 'نوفمبر',
            'December' => 'ديسمبر',
        ];

        $salesChartByMonth = [];
        $oneYears = CarbonPeriod::create($this->firstMonthWithFirstDay, '1 month', $this->lastMonthWithLastDay);

        // تهيئة المصفوفة بصفر لكل شهر
        foreach ($oneYears as $date) {
            $arabicMonth = $months_ar[$date->format('F')];
            $salesChartByMonth[$arabicMonth] = 0;
        }

        $biz = $this->sqlBusinessDateExpr('sale_items.created_at');

        $orders_year = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.retrieve', 0)
            ->where(function ($query) {
                $query->where('sales.sale_type_delivery', 1)
                    ->orWhereNull('sales.sale_type_delivery');
            })
            ->where('sale_items.created_at', '>=', $this->yearQueryStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $this->yearQueryEndExclusive->copy()->timezone('UTC'))
            ->select([
                DB::raw("DATE_FORMAT({$biz}, '%Y-%m') as ym"),
                DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) AS total'),
            ])
            ->groupBy('ym')
            ->orderBy('ym', 'DESC')
            ->get()
            ->toArray();

        // تعبئة القيم
        foreach ($orders_year as $data) {
            $row = is_array($data) ? (object) $data : $data;
            $englishMonth = Carbon::parse($row->ym.'-01')->timezone('Asia/Baghdad')->format('F');
            $arabicMonth = $months_ar[$englishMonth];
            if (isset($salesChartByMonth[$arabicMonth])) {
                $salesChartByMonth[$arabicMonth] += $row->total;
            }
        }

        $data = [];
        $chart = [];
        foreach ($salesChartByMonth as $month => $total) {
            $chart[] = $month;
            $data[] = $total;
        }

        return [$data, $chart];
    }
    public function year_sales_retrieval_sum_statistics()
    {
        $biz = $this->sqlBusinessDateExpr('sale_items.created_at');

        $orders_year = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $this->yearQueryStart->copy()->timezone('UTC'))
            ->where('created_at', '<', $this->yearQueryEndExclusive->copy()->timezone('UTC'))
            ->select([
                DB::raw("DATE_FORMAT({$biz}, '%Y-%m') as ym"),
                DB::raw('SUM(price * returned_quantity) AS total'),
            ])
            ->groupBy('ym')
            ->orderBy('ym', 'DESC')
            ->get()
            ->toArray();

        $months_ar = [
            'January' => 'يناير',
            'February' => 'فبراير',
            'March' => 'مارس',
            'April' => 'أبريل',
            'May' => 'مايو',
            'June' => 'يونيو',
            'July' => 'يوليو',
            'August' => 'أغسطس',
            'September' => 'سبتمبر',
            'October' => 'أكتوبر',
            'November' => 'نوفمبر',
            'December' => 'ديسمبر',
        ];

        $salesChartByMonth = [];
        $oneYears = CarbonPeriod::create($this->firstMonthWithFirstDay, '1 month', $this->lastMonthWithLastDay);

        foreach ($oneYears as $date) {
            $dateString = $date->format('F');
            $arabicMonth = $months_ar[$dateString];
            $salesChartByMonth[$arabicMonth] = 0;
        }

        foreach ($orders_year as $data) {
            $row = is_array($data) ? (object) $data : $data;
            $englishMonth = Carbon::parse($row->ym.'-01')->timezone('Asia/Baghdad')->format('F');
            $arabicMonth = $months_ar[$englishMonth];
            if (isset($salesChartByMonth[$arabicMonth])) {
                $salesChartByMonth[$arabicMonth] += $row->total;
            }
        }

        $data = [];
        $chart = [];
        foreach ($salesChartByMonth as $key => $val) {
            array_push($chart, $key);
            array_push($data, $val);
        }

        return [$data, $chart];
    }

    /**
     * Net sales per calendar day (same filters as year_sales_sum_statistics).
     *
     * @return array{0: array<float>, 1: array<string>} [values, labels as n-j e.g. 4-1]
     */
    public function range_sales_sum_statistics(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->timezone('Asia/Baghdad')->startOfDay();
        $toDay = $to->copy()->timezone('Asia/Baghdad')->startOfDay();
        $rangeStart = $fromDay->copy()->addHours(3);
        $rangeEndExclusive = $toDay->copy()->addDay()->addHours(3);

        $biz = $this->sqlBusinessDateExpr('sale_items.created_at');

        $rows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.retrieve', 0)
            ->where(function ($query) {
                $query->where('sales.sale_type_delivery', 1)
                    ->orWhereNull('sales.sale_type_delivery');
            })
            ->where('sale_items.created_at', '>=', $rangeStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $rangeEndExclusive->copy()->timezone('UTC'))
            ->select([
                DB::raw("{$biz} as day"),
                DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) AS total'),
            ])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $data = [];
        $chart = [];
        foreach (CarbonPeriod::create($fromDay, $toDay) as $date) {
            $d = $date->format('Y-m-d');
            $chart[] = $date->format('n-j');
            $row = $rows->get($d);
            $data[] = $row ? (float) $row->total : 0.0;
        }

        return [$data, $chart];
    }

    /**
     * Return value per day (same logic as year_sales_retrieval_sum_statistics, by day).
     *
     * @return array{0: array<float>, 1: array<string>} labels n-j (month-day, no year)
     */
    public function range_sales_retrieval_sum_statistics(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->timezone('Asia/Baghdad')->startOfDay();
        $toDay = $to->copy()->timezone('Asia/Baghdad')->startOfDay();
        $rangeStart = $fromDay->copy()->addHours(3);
        $rangeEndExclusive = $toDay->copy()->addDay()->addHours(3);

        $biz = $this->sqlBusinessDateExpr('sale_items.created_at');

        $rows = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $rangeStart->copy()->timezone('UTC'))
            ->where('created_at', '<', $rangeEndExclusive->copy()->timezone('UTC'))
            ->select([
                DB::raw("{$biz} as day"),
                DB::raw('SUM(price * returned_quantity) AS total'),
            ])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $data = [];
        $chart = [];
        foreach (CarbonPeriod::create($fromDay, $toDay) as $date) {
            $d = $date->format('Y-m-d');
            $chart[] = $date->format('n-j');
            $row = $rows->get($d);
            $data[] = $row ? (float) $row->total : 0.0;
        }

        return [$data, $chart];
    }

    public function daily_income(int $sale_type = 0)
    {
        $start = $this->businessDayStartBaghdad();
        $end = $this->businessDayEndExclusiveBaghdad($start);

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->where('sale_items.created_at', '>=', $start->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $end->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) as total'))
            ->value('total') ?? 0;
    }

    public function today_sold_items(int $sale_type = 0)
    {
        $start = $this->businessDayStartBaghdad();
        $end = $this->businessDayEndExclusiveBaghdad($start);

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->where('sale_items.created_at', '>=', $start->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $end->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as sold'))
            ->value('sold') ?? 0;
    }

    public function weekly_income(int $sale_type = 0)
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->where('sale_items.created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) as total'))
            ->value('total') ?? 0;
    }

    public function weekly_sold_items(int $sale_type = 0)
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->where('sale_items.created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as sold'))
            ->value('sold') ?? 0;
    }



    public function daily_retrieval()
    {
        $start = $this->businessDayStartBaghdad();
        $end = $this->businessDayEndExclusiveBaghdad($start);

        $daily_retrieval = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $start->copy()->timezone('UTC'))
            ->where('created_at', '<', $end->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(price * returned_quantity) as total'))
            ->value('total');

        return $daily_retrieval ?? 0;
    }

    public function today_retrieval_items()
    {
        $start = $this->businessDayStartBaghdad();
        $end = $this->businessDayEndExclusiveBaghdad($start);

        $today_retrieval_items = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $start->copy()->timezone('UTC'))
            ->where('created_at', '<', $end->copy()->timezone('UTC'))
            ->sum('returned_quantity');

        return $today_retrieval_items;
    }


    public function weekly_retrieval()
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        $weekly_retrieval = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(price * returned_quantity) as total'))
            ->value('total');

        return $weekly_retrieval ?? 0;
    }

    public function weekly_retrieval_items()
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        $weekly_sold_items = Sale_items::where('user_id', auth()->user()->id)
            ->where('returned_quantity', '>', 0)
            ->where('created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->sum('returned_quantity');

        return $weekly_sold_items;
    }


    // debit

    public function debit_income(int $sale_type = 0)
    {
        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->select(DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) as total'))
            ->value('total') ?? 0;
    }

    public function debit_sold_items(int $sale_type = 0)
    {
        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', $sale_type)
            ->select(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as sold'))
            ->value('sold') ?? 0;
    }

    //delivery

    public function delivery_sold_items()
    {
        $start = $this->businessDayStartBaghdad();
        $end = $this->businessDayEndExclusiveBaghdad($start);

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', 2)
            ->where('sale_items.created_at', '>=', $start->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $end->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as sold'))
            ->value('sold') ?? 0;
    }

    public function delivery_weekly_income()
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', 2)
            ->where('sales.sale_type_delivery', 1)
            ->where('sale_items.created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.price * (sale_items.quantity - sale_items.returned_quantity)) as total'))
            ->value('total') ?? 0;
    }

    public function delivery_weekly_retrieval_items()
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', 2)
            ->where('sale_items.created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->sum('sale_items.returned_quantity');
    }

    public function delivery_weekly_sold_items()
    {
        [$weekStart, $weekEndExclusive] = $this->weeklyBusinessWindowBaghdad();

        return Sale_items::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.user_id', auth()->id())
            ->where('sales.sale_type', 2)
            ->where('sale_items.created_at', '>=', $weekStart->copy()->timezone('UTC'))
            ->where('sale_items.created_at', '<', $weekEndExclusive->copy()->timezone('UTC'))
            ->select(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as sold'))
            ->value('sold') ?? 0;
    }

}
