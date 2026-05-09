<?php

namespace App\Http\Controllers;

use App\Models\Sales;
use App\Traits\Filter;
use App\Traits\Search;
use App\Traits\OrderBy;
use App\Traits\Pagination;
use App\Models\DebtorUsers;
use App\Models\PaymentDate;
use App\Traits\SendResponse;
use App\Models\Sale_items;
use Illuminate\Http\Request;
use App\Models\CustomerPaymentHistories;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DebtorUsersController extends Controller
{
    use SendResponse;
    use Pagination;
    use Search;
    use Filter;
    use OrderBy;

    public function getDebtorUsers()
    {
        $debtorUsers = DebtorUsers::select('*');

        if (isset($_GET["query"])) {
            $debtorUsers->where('name', 'LIKE', '%' . $_GET["query"]. '%')
                ->orWhere('phone', 'LIKE', '%' . $_GET["query"] . '%');
        }

        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 1000;
        }
        $res = $this->paging($debtorUsers, $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار جميع المستخدمين بنجاح', [], $res["model"], null, $res["count"]);

    }


    public function getSalesToDebtor()
    {
        $debtorSales = Sales::where("debtor_user_id", $_GET['id'])->with(['paymentDates'])->withCount('sale_items');


        if (isset($_GET["query"])) {
            $debtorSales->where('invoice_number', '=', $_GET["query"]);
        }

        if (!empty($_GET['payment_status'])) {
            $status = $_GET['payment_status'];

            $debtorSales->whereHas('paymentDates', function ($q) use ($status) {
                // unpaid
                if ($status === 'unpaid') {
                    $q->where(function ($s) {
                        $s->where('amount_paid', 0)
                          ->orWhereNull('amount_paid');
                    });
                }

                // paid
                elseif ($status === 'paid') {
                    $q->where('remaining_amount', 0)->where('amount_paid', '!=', 0);
                }

                // partially_paid
                elseif ($status === 'partially_paid') {
                    $q->where('amount_paid', '>', 0)
                      ->where('remaining_amount', '>', 0);
                }
            });
        }


        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }
        $res = $this->paging($debtorSales->orderBy("created_at", "DESC"), $_GET['skip'], $_GET['limit']);

        // 👇 هنا نستدعي الـ attribute يدويًا ونضيفه للنتائج
        $res["model"]->transform(function ($sale) {
            $sale->payment_status = $sale->payment_status;
            $sale->items_count = $sale->sale_items_count;
            unset($sale->sale_items_count, $sale->sale_items);
            return $sale;
        });

        return $this->send_response(200, 'تم احضار جميع المبيعات الاجل بنجاح', [], $res["model"], null, $res["count"]);

    }

    public function getSalesToDebtorWithPaymentsItems()
    {
        // اجلب الفاتورة حسب ID مع العلاقات
        $sale = Sales::with(['paymentDates.customer_payment_histories', 'sale_items'])->find($_GET['id']);

        // إذا لم يتم العثور على الفاتورة
        if (!$sale) {
            return $this->send_response(404, 'الفاتورة غير موجودة');
        }

        // استدعاء الـ accessor الخاص بـ payment_status إن وجد
        $sale->payment_status = $sale->payment_status;

        return $this->send_response(200, 'تم احضار جميع المبيعات الاجل مع العناصر و الدفعات بنجاح', [], $sale);

    }

    public function getStatisticsDebtorUser()
    {

        $debtorUser = DebtorUsers::findOrFail($_GET['id']);

        // إجمالي عدد المبيعات لهذا المستخدم
        $count_sales = Sales::where('debtor_user_id', $debtorUser->id)->count();

        // جلب جميع السجلات الخاصة بالمدفوعات لهذا المستخدم
        $payments = PaymentDate::where('debtor_user_id', $debtorUser->id)->get();

        // الإحصائيات
        $unpaid = $payments->where('amount_paid', 0)->count();
        $paid = $payments->where('remaining_amount', 0)->where('amount_paid', '!=', 0)->count();

        // المدفوعة جزئياً: ليست صفر وليست كاملة
        $partially_paid = $payments
            ->filter(function ($p) {
                return $p->amount_paid > 0 && $p->remaining_amount > 0;
            })
            ->count();

        $data = [
            'debtorUser' => $debtorUser,
            'total_sales' => $count_sales,
            'unpaid' => $unpaid,
            'paid' => $paid,
            'partially_paid' => $partially_paid,
        ];
        return $this->send_response(200, 'تم احضار بيانات الزبون بنجاح', [], $data);
    }


    public function addDebtorUsers(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'phone' => 'nullable|string|min:11|max:11',
            "name" => "required|string|max:100|min:3",
            "city" => "nullable|string|max:100|min:3",


        ], [
            'name.required' => 'يرجى ادخال اسم الزبون',
            'name.string' => 'اسم الزبون يجب ان يكون نص',
            'name.max' => 'اسم الزبون يجب ان لا يزيد عن 100 حرف',
            'name.min' => 'اسم الزبون يجب ان لا يقل عن 3 حرف',
            'phone.string' => 'رقم الهاتف يجب ان يكون نص',
            'phone.min' => 'رقم الهاتف يجب ان لا يقل عن 8 ارقام',
            'phone.max' => 'رقم الهاتف يجب ان لا يزيد عن 8 ارقام',
            'city.string' => 'اسم المدينة يجب ان يكون نص',
            'city.max' => 'اسم المدينة يجب ان لا يزيد عن 100 حرف',
            'city.min' => 'اسم المدينة يجب ان لا يقل عن 3 حرف',

        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $data = [
            'name' => $request['name'],
            'phone' => $request['phone'],
            'city' => $request['city'],
            'remaining_amount' => 0,
        ];
        $debtorUsers = DebtorUsers::create($data);
        return $this->send_response(200, 'تم إضافة المستخدم بنجاح', [], DebtorUsers::find($debtorUsers->id));
    }

    public function editDebtorUsers(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'id' => 'required|uuid|exists:debtor_users,id',
            'phone' => 'nullable|string|min:11|max:11',
            "name" => "required|string|max:100|min:3",
            "city" => "nullable|string|max:100|min:3",
        ], [
            'id.required' => 'يرجى ادخال معرف المستخدم',
            'id.uuid' => 'معرف المستخدم غير صالح',
            'id.exists' => 'المستخدم غير موجود',
            'name.required' => 'يرجى ادخال اسم الزبون',
            'name.string' => 'اسم الزبون يجب ان يكون نص',
            'name.max' => 'اسم الزبون يجب ان لا يزيد عن 100 حرف',
            'name.min' => 'اسم الزبون يجب ان لا يقل عن 3 حرف',
            'phone.string' => 'رقم الهاتف يجب ان يكون نص',
            'phone.min' => 'رقم الهاتف يجب ان لا يقل عن 8 ارقام',
            'phone.max' => 'رقم الهاتف يجب ان لا يزيد عن 8 ارقام',
            'city.string' => 'اسم المدينة يجب ان يكون نص',
            'city.max' => 'اسم المدينة يجب ان لا يزيد عن 100 حرف',
            'city.min' => 'اسم المدينة يجب ان لا يقل عن 3 حرف',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }
        $debtorUsers = DebtorUsers::find($request['id']);

        $debtorUsers->update([
            'name' => $request['name'],
            'phone' => $request['phone'],
            'city' => $request['city'],
        ]);

        return $this->send_response(200, 'تم تعديل معلومات الزبون بنجاح', [], DebtorUsers::find($debtorUsers->id));
    }

    public function addNewPayment(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'payment_date_id' => 'required|uuid|exists:payment_dates,id',
            'debtor_user_id' => 'required|uuid|exists:debtor_users,id',
            'amount_paid' => 'required|numeric|min:0',
        ], [
            'payment_date_id.required' => 'يرجى ادخال معرف تاريخ الدفعة',
            'payment_date_id.uuid' => 'معرف تاريخ الدفعة غير صالح',
            'payment_date_id.exists' => 'تاريخ الدفعة غير موجود',

            'debtor_user_id.required' => 'يرجى ادخال معرف المدين',
            'debtor_user_id.uuid' => 'معرف المدين غير صالح',
            'debtor_user_id.exists' => 'المدين غير موجود',

            'amount_paid.required' => 'يرجى ادخال مبلغ الدفع',
            'amount_paid.numeric' => 'مبلغ الدفع يجب ان يكون رقم',
            'amount_paid.min' => 'مبلغ الدفع يجب ان لا يقل عن 0',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $debtorUsers = DebtorUsers::find($request['debtor_user_id']);

        if ($request['amount_paid'] > $debtorUsers->remaining_amount) {
            return $this->send_response(400, "مبلغ الدفع أكبر من المبلغ المتبقي", [], []);
        }
        $debtorUsers->remaining_amount -= $request['amount_paid'];
        $debtorUsers->save();

        $payment_date = PaymentDate::find($request['payment_date_id']);
        $payment_date->amount_paid += $request['amount_paid'];
        $payment_date->remaining_amount -= $request['amount_paid'];
        $payment_date->save();

        $customer_payment_histories = CustomerPaymentHistories::create([
            'debtor_user_id' => $debtorUsers->id,
            'payment_date_id' => $payment_date->id,
            'amount_paid' => $request['amount_paid'],
        ]);


        $sale = Sales::with(['paymentDates.customer_payment_histories', 'sale_items','debtorUser'])->find($payment_date->sale_id);

        // استدعاء الـ accessor الخاص بـ payment_status إن وجد
        $sale->payment_status = $sale->payment_status;

        return $this->send_response(200, 'تم احضار اضافة دفعة بنجاح', [], $sale);
    }

    public function retrieveItemDebtor(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
            'sale_item_id' => 'required|uuid|exists:sale_items,id',
            'payment_date_id' => 'required|uuid|exists:payment_dates,id',
            'debtor_user_id' => 'required|uuid|exists:debtor_users,id',
            'sale_id' => 'required|uuid|exists:sales,id',
            'quantity' => 'required|integer|min:1',
        ], [
            'sale_item_id.required' => 'معرف العنصر مطلوب',
            'sale_item_id.exists' => 'العنصر غير موجود',
            'quantity.required' => 'الكمية مطلوبة',

            'payment_date_id.required' => 'يرجى ادخال معرف تاريخ الدفعة',
            'payment_date_id.uuid' => 'معرف تاريخ الدفعة غير صالح',
            'payment_date_id.exists' => 'تاريخ الدفعة غير موجود',

            'debtor_user_id.required' => 'يرجى ادخال معرف المدين',
            'debtor_user_id.uuid' => 'معرف المدين غير صالح',
            'debtor_user_id.exists' => 'المدين غير موجود',

            'sale_id.required' => 'يرجى ادخال معرف الفاتورة',
            'sale_id.uuid' => 'معرف الفاتورة غير صالح',
            'sale_id.exists' => 'الفاتورة غير موجود',

        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $saleItem = Sale_items::find($requestData['sale_item_id']);

        // تأكد من أن الكمية المرجعة لا تتجاوز المباعة
        if ($requestData['quantity'] > ($saleItem->quantity - $saleItem->returned_quantity)) {
            return $this->send_response(400, 'الكمية المرجعة تتجاوز الكمية المباعة.', [], []);
        }



        // تحديث الكمية المرجعة
        $saleItem->returned_quantity += $requestData['quantity'];
        $saleItem->save();

        // استرجاع القطع للمخزون
        $item = $saleItem->item;
        $item->quantity += $requestData['quantity'];
        $item->status = 'available';
        $item->save();

        // تعديل المبلغ الكلي للفاتورة
        $sale = $saleItem->sale;
        $return_amount = $saleItem->price * $requestData['quantity'];
        $sale->total -= $return_amount;
        $sale->save();

        // إذا جميع العناصر في الفاتورة تم إرجاعها بالكامل → غيّر حالة الفاتورة إلى "راجعة بالكامل"
        $allReturned = $sale->sale_items->every(function ($item) {
            return $item->returned_quantity >= $item->quantity;
        });

        if ($allReturned) {
            $sale->retrieve = 1;
            $sale->total = 0; // ← تصفير الإجمالي
            $sale->save();
        }



        $payment_date = PaymentDate::find($request['payment_date_id']);
        if ($sale->total == 0) {
            // ✅ إرجاع كامل للفواتير → خصم كل ما دُفع (إعادة المبلغ بالكامل)
            $payment_date->amount_paid = 0;
        } else {
            // ✅ إرجاع جزئي
            // تحقق هل الزبون دفع أكثر من السعر الجديد بعد الإرجاع
            if ($payment_date->amount_paid > $sale->total) {
                // المبلغ الزائد الذي يجب خصمه (ردّه)
                $overpaid = $payment_date->amount_paid - $sale->total;

                // لا يمكن أن يكون أكثر من قيمة العناصر المرجعة
                $amount_to_deduct = min($overpaid, $return_amount);

                $payment_date->amount_paid -= $amount_to_deduct;
            }
        }

        $payment_date->remaining_amount = $sale->total - $payment_date->amount_paid;
        if ($payment_date->remaining_amount < 0) {
            $payment_date->remaining_amount = 0;
        }

        $payment_date->save();


        // ✅ إعادة حساب إجمالي المستحق الحقيقي للزبون بعد أي إرجاع


        $totalRemaining = PaymentDate::where('debtor_user_id', $request['debtor_user_id'])
            ->sum(DB::raw('COALESCE(remaining_amount, 0)'));

        $debtorUsers = DebtorUsers::find($request['debtor_user_id']);
        $debtorUsers->remaining_amount = max(0, floatval($totalRemaining));
        $debtorUsers->save();


        $sale = Sales::with(['paymentDates.customer_payment_histories', 'sale_items'])->find($payment_date->sale_id);

        // استدعاء الـ accessor الخاص بـ payment_status إن وجد
        $sale->payment_status = $sale->payment_status;

        return $this->send_response(200, 'تم استرجاع العناصر بنجاح', [], [
            'sale' => $sale,
            'debtor_user_remaining' => $debtorUsers->remaining_amount,
        ]);

    }


    public function getInvoiceToPrint(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'id' => 'required|uuid|exists:debtor_users,id',
        ], [
           'id.required' => 'يرجى ادخال معرف المستخدم',
            'id.uuid' => 'معرف المستخدم غير صالح',
            'id.exists' => 'المستخدم غير موجود',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }


        $debtorUsersQuery = DebtorUsers::where('id', $request['id'])
        ->with(['sales' => function ($q) {
            // 🔹 جلب فقط المبيعات unpaid أو partially_paid
            $q->whereHas('paymentDates', function ($query) {
                $query->where(function ($s) {
                    $s->where('amount_paid', 0)
                      ->orWhereNull('amount_paid');
                })
                ->orWhere(function ($s) {
                    $s->where('amount_paid', '>', 0)
                      ->where('remaining_amount', '>', 0);
                });
            });

            // 🔹 استبعاد المبيعات المسترجعة بالكامل (retrieve = 1)
            $q->where('retrieve', '!=', 1);


            // 🔹 نحسب عدد الأصناف داخل sale_items
            $q->withCount('sale_items as sale_items_count');

            // 🔹 نحسب مجموع الكمية - المرتجع
            $q->withSum('sale_items as total_quantity', DB::raw('quantity - returned_quantity'));

            // 🔹 نضيف علاقة الدفع فقط (بدون sale_items)
            $q->with('paymentDates');
        }]);

        $debtorUser = $debtorUsersQuery->first();

        return $this->send_response(200, 'تم جلب بيانات الفواتير بنجاح', [], $debtorUser);
    }



    public function addGeneralPayment(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'id' => 'required|uuid|exists:debtor_users,id',
            'amount_paid' => 'required|numeric|min:0',
        ], [
            'id.required' => 'يرجى ادخال معرف المدين',
            'id.uuid' => 'معرف المدين غير صالح',
            'id.exists' => 'المدين غير موجود',

            'amount_paid.required' => 'يرجى ادخال مبلغ الدفع',
            'amount_paid.numeric' => 'مبلغ الدفع يجب ان يكون رقم',
            'amount_paid.min' => 'مبلغ الدفع يجب ان لا يقل عن 0',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $debtorUsers = DebtorUsers::find($request['id']);

        if ($request['amount_paid'] > $debtorUsers->remaining_amount) {
            return $this->send_response(400, "مبلغ الدفع أكبر من المبلغ المتبقي", [], []);
        }
        $debtorUsers->remaining_amount -= $request['amount_paid'];
        $debtorUsers->save();



        $payment_dates = PaymentDate::where(function ($q) {
            // 🟥 غير مدفوعة (unpaid)
            $q->where(function ($s) {
                $s->where('amount_paid', 0)
                  ->orWhereNull('amount_paid');
            })
            // 🟨 مدفوعة جزئياً (partially_paid)
            ->orWhere(function ($s) {
                $s->where('amount_paid', '>', 0)
                  ->where('remaining_amount', '>', 0);
            });
        })
        ->where('debtor_user_id', $debtorUsers->id) // المستخدم المطلوب
        ->orderBy('created_at', 'asc')              // ⬅️ من الأقدم إلى الأحدث
        ->get();


        $remainingPayment = $request['amount_paid'];

        foreach ($payment_dates as $payment) {
            if ($remainingPayment <= 0) {
                break;
            } // انتهى المبلغ

            $remaining_amount = (float) $payment->remaining_amount;
            $amount_paid = (float) $payment->amount_paid;

            // المبلغ الذي سيتم دفعه لهذه الفاتورة
            $amountToPay = 0;

            if ($remainingPayment >= $remaining_amount) {
                // ✅ سدّد الفاتورة بالكامل
                $amountToPay = $remaining_amount;
                $payment->amount_paid = $amount_paid + $remaining_amount;
                $payment->remaining_amount = 0;
                $remainingPayment -= $remaining_amount;
            } else {
                // 🟡 سدّد جزئياً
                $amountToPay = $remainingPayment;
                $payment->amount_paid = $amount_paid + $remainingPayment;
                $payment->remaining_amount = $remaining_amount - $remainingPayment;
                $remainingPayment = 0;
            }

            $payment->save();

            // 🧾 حفظ سجل الدفع في customer_payment_histories
            CustomerPaymentHistories::create([
                'debtor_user_id' => $debtorUsers->id,
                'payment_date_id' => $payment->id,
                'amount_paid' => $amountToPay,
            ]);
        }

        return $this->send_response(200, 'تم احضار اضافة دفعة بنجاح', [], []);
    }


    public function changeDebitType(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
            'payment_date_id' => 'required|uuid|exists:payment_dates,id',
            'debtor_user_id' => 'required|uuid|exists:debtor_users,id',
            'sale_id' => 'required|uuid|exists:sales,id',

        ], [

            'payment_date_id.required' => 'يرجى ادخال معرف تاريخ الدفعة',
            'payment_date_id.uuid' => 'معرف تاريخ الدفعة غير صالح',
            'payment_date_id.exists' => 'تاريخ الدفعة غير موجود',

            'debtor_user_id.required' => 'يرجى ادخال معرف المدين',
            'debtor_user_id.uuid' => 'معرف المدين غير صالح',
            'debtor_user_id.exists' => 'المدين غير موجود',

            'sale_id.required' => 'يرجى ادخال معرف الفاتورة',
            'sale_id.uuid' => 'معرف الفاتورة غير صالح',
            'sale_id.exists' => 'الفاتورة غير موجود',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $sale = Sales::find($requestData['sale_id']);

        $sale->sale_type = 0;
        $sale->debtor_user_id = null;
        $sale->save();


        // جلب الفاتورة والمدين وتاريخ الدفعة
        $sale = Sales::find($requestData['sale_id']);
        $debtor = DebtorUsers::find($requestData['debtor_user_id']);
        $paymentDate = PaymentDate::find($requestData['payment_date_id']);

        if (!$sale || !$debtor || !$paymentDate) {
            return $this->send_response(402, "تعذر العثور على أحد السجلات المطلوبة", [], []);
        }

        // استرجاع المبلغ المتبقي من المدين
        $remainingToSubtract = $sale->total - ($paymentDate->amount_paid ?? 0);

        // تقليل الرصيد المتبقي للمدين
        if ($debtor->remaining_amount >= $remainingToSubtract) {
            $debtor->remaining_amount -= $remainingToSubtract;
        } else {
            // إذا حدث خلل (بسبب تداخل عمليات أو خطأ سابق)
            $debtor->remaining_amount = 0;
        }
        $debtor->save();

        // حذف جميع سجلات الدفع الخاصة بهذه الفاتورة
        CustomerPaymentHistories::where('payment_date_id', $paymentDate->id)->delete();
        $paymentDate->delete();

        // تحويل الفاتورة إلى نقدي
        $sale->sale_type = 0;
        $sale->debtor_user_id = null;
        $sale->save();

        return $this->send_response(200, "تم تحويل الفاتورة من آجل إلى نقدي بنجاح.", [], []);

    }
}
