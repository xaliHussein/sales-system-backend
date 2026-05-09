<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\SendResponse;
use App\Traits\Statistics;
use Illuminate\Support\Facades\Validator;

class StatisticsController extends Controller
{
    use SendResponse;
    use Statistics;

    public function getStatistics()
    {

        $dailyIncome = $this->daily_income(0);
        $todaySoldItems = $this->today_sold_items(0);
        $weeklyIncome = $this->weekly_income(0);
        $weeklySoldItems = $this->weekly_sold_items(0);

        $deliveryWeeklyRetrievalItems = $this->delivery_weekly_retrieval_items();
        $todaySoldItemsDelivery = $this->delivery_sold_items();
        $weeklyIncomeDelivery = $this->delivery_weekly_income();
        $weeklySoldItemsDelivery = $this->delivery_weekly_sold_items();


        $dailyIncomeDebt = $this->daily_income(1);
        $todaySoldItemsDebt = $this->today_sold_items(1);
        $allIncomeDebt = $this->debit_income(1);
        $allSoldItemsDebt = $this->debit_sold_items(1);

        $dailyRetrieval = $this->daily_retrieval();
        $todayRetrievalItems = $this->today_retrieval_items();
        $weeklyRetrieval = $this->weekly_retrieval();
        $weeklyRetrievalItems = $this->weekly_retrieval_items();

        $yearSalesSumStatistics = $this->year_sales_sum_statistics();
        $yearSalesRetrievalSumStatistics = $this->year_sales_retrieval_sum_statistics();

        $data = [
            'dailyIncome' => $dailyIncome,
            'todaySoldItems' => $todaySoldItems,
            'weeklyIncome' => $weeklyIncome,
            'weeklySoldItems' => $weeklySoldItems,

            'deliveryWeeklyRetrievalItems' => $deliveryWeeklyRetrievalItems,
            'todaySoldItemsDelivery' => $todaySoldItemsDelivery,
            'weeklyIncomeDelivery' => $weeklyIncomeDelivery,
            'weeklySoldItemsDelivery' => $weeklySoldItemsDelivery,

            'dailyIncomeDebt' => $dailyIncomeDebt,
            'todaySoldItemsDebt' => $todaySoldItemsDebt,
            'allIncomeDebt' => $allIncomeDebt,
            'allSoldItemsDebt' => $allSoldItemsDebt,

            'dailyRetrieval' => $dailyRetrieval,
            'todayRetrievalItems' => $todayRetrievalItems,
            'weeklyRetrieval' => $weeklyRetrieval,
            'weeklyRetrievalItems' => $weeklyRetrievalItems,

            'yearSalesSumStatistics' => $yearSalesSumStatistics,
            'yearSalesRetrievalSumStatistics' => $yearSalesRetrievalSumStatistics
        ];

        return $this->send_response(200, 'تم احضار الاحصائيات بنجاح', [], $data);

    }

    public function getRangeSalesStatistics(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d',
        ], [
            'date_from.required' => 'تاريخ البداية مطلوب',
            'date_from.date_format' => 'صيغة تاريخ البداية يجب أن تكون Y-m-d',
            'date_to.required' => 'تاريخ النهاية مطلوب',
            'date_to.date_format' => 'صيغة تاريخ النهاية يجب أن تكون Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->send_response(400, 'خطأ في المدخلات', $validator->errors(), []);
        }

        $from = Carbon::parse($request->query('date_from'), 'Asia/Baghdad')->startOfDay();
        $to = Carbon::parse($request->query('date_to'), 'Asia/Baghdad')->endOfDay();

        if ($from->gt($to)) {
            return $this->send_response(400, 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية', [], []);
        }

        $inclusiveDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        if ($inclusiveDays > 366) {
            return $this->send_response(400, 'النطاق الزمني يجب ألا يتجاوز 366 يوماً', [], []);
        }

        $rangeSalesSumStatistics = $this->range_sales_sum_statistics($from, $to);
        $rangeSalesRetrievalSumStatistics = $this->range_sales_retrieval_sum_statistics($from, $to);

        $payload = [
            'rangeSalesSumStatistics' => $rangeSalesSumStatistics,
            'rangeSalesRetrievalSumStatistics' => $rangeSalesRetrievalSumStatistics,
            'date_from' => $from->toDateString(),
            'date_to' => $to->copy()->startOfDay()->toDateString(),
        ];

        return $this->send_response(200, 'تم احضار إحصائيات المبيعات للفترة', [], $payload);
    }
}
