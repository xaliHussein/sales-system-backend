<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Items;
use App\Models\Sales;
use App\Models\Dozens;
use App\Traits\Filter;
use App\Traits\Search;
use App\Models\Product;
use App\Traits\OrderBy;
use App\Models\Sale_items;
use App\Traits\Pagination;
use App\Models\DebtorUsers;
use App\Models\PaymentDate;
use App\Traits\UploadImage;
use App\Traits\SendResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\CustomerPaymentHistories;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    use SendResponse;
    use UploadImage;
    use Pagination;
    use Search;
    use Filter;
    use OrderBy;

    public function random_code()
    {
        $code = substr(str_shuffle("0123456789ABCDEFGHIJKPQWRZX"), 0, 10);
        $get = Sales::where('invoice_number', $code)->first();
        if ($get) {
            return $this->random_code();
        } else {
            return $code;
        }
    }

    public function getSales()
    {
        $sales = Sales::where("user_id", auth()->user()->id)->with(['sale_items'])->where('sale_type', '=', 0);
        if (isset($_GET["query"])) {
            $sales->where('invoice_number', '=', $_GET["query"]);
        }

        if (isset($_GET["filter_date"])) {
            $date = json_decode($_GET["filter_date"]);

            $start = Carbon::parse($date->start_date, 'Asia/Baghdad')->startOfDay();
            $end = Carbon::parse($date->end_date, 'Asia/Baghdad')->endOfDay();

            $sales->whereBetween('created_at', [$start, $end]);
        }

        if (isset($_GET["status"])) {
            $status = $_GET["status"];

            if ($status === 'full_return') {
                $sales->where('retrieve', 1);
            } elseif ($status === 'partial_return') {
                $sales->where('retrieve', '!=', 1)
                      ->whereHas('sale_items', function ($q) {
                          $q->where('returned_quantity', '>', 0);
                      });
            } elseif ($status === 'completed') {
                $sales->where('retrieve', '!=', 1)
                      ->whereDoesntHave('sale_items', function ($q) {
                          $q->where('returned_quantity', '>', 0);
                      });
            }

        }

        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }

        $res = $this->paging($sales->orderBy("created_at", "DESC"), $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار جميع المبيعات', [], $res["model"], null, $res["count"]);
    }


    public function getSalesDelivery()
    {
        $sales = Sales::where("user_id", auth()->user()->id)->with(['sale_items'])->where('sale_type', 2);
        if (isset($_GET["query"])) {
            $sales->where('invoice_number', '=', $_GET["query"]);
        }

        if (isset($_GET["filter_date"])) {
            $date = json_decode($_GET["filter_date"]);

            $start = Carbon::parse($date->start_date, 'Asia/Baghdad')->startOfDay();
            $end = Carbon::parse($date->end_date, 'Asia/Baghdad')->endOfDay();

            $sales->whereBetween('created_at', [$start, $end]);
        }

        if (isset($_GET["sale_type_delivery"])) {
            if ($_GET["sale_type_delivery"] == "progress") {
                $sales->where('sale_type_delivery', 0);
            } elseif ($_GET["sale_type_delivery"] == "delivered") {
                $sales->where('sale_type_delivery', 1);
            }
        }

        if (isset($_GET["status"])) {
            $status = $_GET["status"];

            if ($status === 'full_return') {
                $sales->where('retrieve', 1);
            } elseif ($status === 'partial_return') {
                $sales->where('retrieve', '!=', 1)
                      ->whereHas('sale_items', function ($q) {
                          $q->where('returned_quantity', '>', 0);
                      });
            } elseif ($status === 'completed') {
                $sales->where('retrieve', '!=', 1)
                      ->whereDoesntHave('sale_items', function ($q) {
                          $q->where('returned_quantity', '>', 0);
                      });
            }

        }

        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }

        $res = $this->paging($sales->orderBy("created_at", "DESC"), $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار جميع المبيعات', [], $res["model"], null, $res["count"]);
    }

    public function addSales(Request $request)
    {
        $requestData = $request->json()->all();

        $data_sales = [];
        $rules = [
          'variants' => 'required|array',
          'variants.*.id' => 'required|exists:items,id',
          'variants.*.quantity' => 'required|integer|min:1',
          'variants.*.dozen_id' => 'required|exists:dozens,id',
          'sale_type' => 'required|in:0,1,2',

          'payment_amount' => 'required_if:sale_type,1|nullable|numeric|min:1',
          'name' => 'required_if:sale_type,2|nullable|string|max:100|min:1',
          'city' => 'required_if:sale_type,2|nullable|string|max:100|min:1',
          'phone' => 'required_if:sale_type,2|nullable|string|min:11|max:11',
        ];
        $messages = [
            'variants.required' => 'معرف المتغيرات مطلوب',
            'variants.array' => 'المتغيرات يجب أن تكون مصفوفة',
            'variants.*.id.required' => 'معرف العنصر مطلوب',
            'variants.*.id.exists' => 'العنصر غير موجود',
            'variants.*.quantity.required' => 'الكمية مطلوبة',
            'variants.*.quantity.integer' => 'الكمية يجب أن تكون عدد صحيح',
            'variants.*.quantity.min' => 'الكمية يجب أن تكون أكبر من أو تساوي 1',
            'variants.*.dozen_id.required' => 'معرف الدزينة مطلوب',
            'variants.*.dozen_id.exists' => 'الدزينة غير موجودة',
            'sale_type.required' => 'نوع البيع مطلوب',
            'sale_type.in' => 'نوع البيع غير صالح',
        ];

        if ($requestData['sale_type'] == 1) {
            $rules['debtor_id'] = 'required|exists:debtor_users,id';
            $messages['debtor_id.required'] =  'معرف المدين مطلوب';
            $messages['debtor_id.exists'] =  'المدين غير موجود';

            $rules['payment_amount'] = 'nullable|numeric|min:1';

            $data_sales['debtor_user_id'] = $requestData['debtor_id'];
        }

        $validator = Validator::make($requestData, $rules, $messages);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        return DB::transaction(function () use ($requestData, $data_sales) {
            $variants = $requestData['variants'];
            usort($variants, function ($a, $b) {
                return strcmp($a['id'], $b['id']);
            });

            $total_price = 0;
            $sales_items_data = [];

            foreach ($variants as $variant) {
                $item = Items::where('id', $variant['id'])
                    ->where('user_id', auth()->user()->id)
                    ->where('dozen_id', $variant['dozen_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$item) {
                    return $this->send_response(400, 'العنصر غير موجود أو لا يتبع هذه الدزينة', [], []);
                }

                $dozen = Dozens::where('id', $variant['dozen_id'])
                    ->where('user_id', auth()->user()->id)
                    ->first();
                if (!$dozen) {
                    return $this->send_response(400, 'الدزينة غير موجودة', [], []);
                }

                if ($item->quantity < $variant['quantity']) {
                    return $this->send_response(400, 'الكمية المطلوبة أكبر من المتاحة لاحد العناصر', [], []);
                }

                $quantity = $item->quantity - $variant['quantity'];
                $data_item = ['quantity' => $quantity];
                if ($quantity == 0) {
                    $data_item['status'] = 'sold_out';
                }
                $item->update($data_item);

                $total_price += $variant['final_price'] * $variant['quantity'];
                $sales_items_data[] = [
                    'item_id' => $variant['id'],
                    'dozen_price' => $variant['final_price'],
                    'color' => $dozen->color,
                    'product_name' => $dozen->product->name,
                    'size' => $item->size,
                    'barcode' => $dozen->barcode,
                    'quantity' => $variant['quantity'],
                ];
            }

            $data_sales['user_id'] = auth()->user()->id;
            $data_sales['invoice_number'] = $this->random_code();
            $data_sales['total'] = $total_price;
            $data_sales['sale_type'] = $requestData['sale_type'];

            $paymentDate = null;

            if ($requestData['sale_type'] == 1) {
                $debtor = DebtorUsers::where('id', $requestData['debtor_id'])->lockForUpdate()->first();
                if (!$debtor) {
                    return $this->send_response(400, 'المدين غير موجود', [], []);
                }
                $debtor->remaining_amount = $debtor->remaining_amount + ($total_price - $requestData['payment_amount']);
                $debtor->save();

                $paymentDate = PaymentDate::create([
                    'debtor_user_id' => $debtor->id,
                    'amount_paid' => $requestData['payment_amount'] ?? 0,
                    'remaining_amount' => ($total_price - $requestData['payment_amount']),
                ]);
                CustomerPaymentHistories::create([
                    'debtor_user_id' => $debtor->id,
                    'payment_date_id' => $paymentDate->id,
                    'amount_paid' => $requestData['payment_amount'] ?? 0,
                ]);

                $data_sales['debtor_user_id'] = $debtor->id;
            }

            if ($requestData['sale_type'] == 2) {
                $data_sales['sale_type_delivery'] = 0;
                $data_sales['name'] = $requestData['name'];
                $data_sales['city'] = $requestData['city'];
                $data_sales['phone'] = $requestData['phone'];
            }

            $sales = Sales::create($data_sales);

            if ($requestData['sale_type'] == 1 && $paymentDate) {
                $paymentDate->update([
                    'sale_id' => $sales->id,
                ]);
            }

            foreach ($sales_items_data as $item_data) {
                Sale_items::create([
                    'user_id' => auth()->user()->id,
                    'sale_id' => $sales->id,
                    'item_id' => $item_data['item_id'],
                    'price' => $item_data['dozen_price'],
                    'quantity' => $item_data['quantity'],
                    'color' => $item_data['color'],
                    'product_name' => $item_data['product_name'],
                    'size' => $item_data['size'],
                    'barcode' => $item_data['barcode'],
                ]);
            }

            return $this->send_response(200, 'تمت العملية بنجاح', [], Sales::with(['sale_items', 'paymentDates', 'debtorUser'])->find($sales->id));
        });
    }


    public function retrieveItem(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
           'sale_item_id' => 'required|uuid|exists:sale_items,id',
           'quantity' => 'required|integer|min:1',
        ], [
            'sale_item_id.required' => 'معرف العنصر مطلوب',
            'sale_item_id.exists' => 'العنصر غير موجود',
            'quantity.required' => 'الكمية مطلوبة',
        ]);


        return DB::transaction(function () use ($requestData) {
            $saleItem = Sale_items::where('id', $requestData['sale_item_id'])->lockForUpdate()->first();
            if (!$saleItem) {
                return $this->send_response(400, 'عنصر الفاتورة غير موجود.', [], []);
            }

            // تأكد من أن الكمية المرجعة لا تتجاوز المباعة
            if ($requestData['quantity'] > ($saleItem->quantity - $saleItem->returned_quantity)) {
                return $this->send_response(400, 'الكمية المرجعة تتجاوز الكمية المباعة.', [], []);
            }

            // تحديث الكمية المرجعة
            $saleItem->returned_quantity += $requestData['quantity'];
            $saleItem->save();

            // استرجاع القطع للمخزون
            $item = Items::where('id', $saleItem->item_id)->lockForUpdate()->first();
            if (!$item) {
                return $this->send_response(400, 'عنصر المخزون غير موجود.', [], []);
            }
            $item->quantity += $requestData['quantity'];
            $item->status = 'available';
            $item->save();

            // تعديل المبلغ الكلي للفاتورة
            $sale = $saleItem->sale;
            $sale->total -= ($saleItem->price * $requestData['quantity']);
            $sale->save();

            // إذا جميع العناصر في الفاتورة تم إرجاعها بالكامل → غيّر حالة الفاتورة إلى "راجعة بالكامل"
            $sale->load('sale_items');
            $allReturned = $sale->sale_items->every(function ($line) {
                return $line->returned_quantity >= $line->quantity;
            });

            if ($allReturned) {
                $sale->retrieve = 1;
                $sale->total = 0; // ← تصفير الإجمالي
                $sale->save();
            }

            return $this->send_response(200, 'تم إرجاع المنتج بنجاح.', [], Sales::with(['sale_items'])->find($sale->id));
        });
    }

    public function retrieveAllItems(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
           'sale_id' => 'required|uuid|exists:sales,id',
        ], [
            'sale_id.required' => 'معرف العنصر مطلوب',
            'sale_id.exists' => 'العنصر غير موجود',
        ]);


        return DB::transaction(function () use ($requestData) {
            $sale = Sales::where('id', $requestData['sale_id'])->lockForUpdate()->first();
            $saleItems = Sale_items::where('sale_id', $sale->id)->lockForUpdate()->get();

            foreach ($saleItems as $saleItem) {
                $item = Items::where('id', $saleItem->item_id)->lockForUpdate()->first();

                if ($item) {
                    $qtyToRestore = $saleItem->quantity - $saleItem->returned_quantity;
                    if ($qtyToRestore > 0) {
                        $item->quantity += $qtyToRestore;
                        $item->status = 'available';
                        $item->save();
                    }
                }

                // تعيين الكمية المرجعة كاملة
                $saleItem->returned_quantity = $saleItem->quantity;
                $saleItem->save();
            }

            // وضع علامة أن الفاتورة مرجعة بالكامل
            $sale->retrieve = 1;
            $sale->save();

            return $this->send_response(200, 'تم إرجاع كامل الفاتورة بنجاح.', [], Sales::with(['sale_items'])->find($sale->id));
        });
    }


    public function changeTypeDelivery(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
           'sale_id' => 'required|uuid|exists:sales,id',
        ], [
            'sale_id.required' => 'معرف العنصر مطلوب',
            'sale_id.exists' => 'العنصر غير موجود',
        ]);


        $sale = Sales::find($requestData['sale_id']);
        $sale->sale_type_delivery = 1;
        $sale->save();
        return $this->send_response(200, 'تم تغير حالة التوصيل بنجاح.', [], Sales::with(['sale_items'])->find($sale->id));
    }

    public function changeSaleType(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
           'sale_id' => 'required|uuid|exists:sales,id',
           'debtor_id' => 'required|exists:debtor_users,id',
           'payment_amount' => 'nullable|numeric|min:1',
           'sale_type' => 'required|in:1',
        ], [
            'sale_id.required' => 'معرف الفاتورة مطلوب',
            'sale_id.exists' => 'هذه الفاتورة غير موجود',

            'debtor_id.required' => 'معرف المدين مطلوب',
            'debtor_id.exists' => 'المدين غير موجود',

            'sale_type.required' => 'نوع البيع مطلوب',
            'sale_type.in' => 'نوع البيع غير صالح',
        ]);


        $sale = Sales::find($requestData['sale_id']);

        $sale->sale_type = 1;
        $sale->debtor_user_id = $requestData['debtor_id'];
        $sale->save();

        $debtor = DebtorUsers::find($requestData['debtor_id']);
        $debtor->remaining_amount = $debtor->remaining_amount + ($sale->total - $requestData['payment_amount']);
        $debtor->save();

        $paymentDate = PaymentDate::create([
            'debtor_user_id' => $debtor->id,
            'amount_paid' => $requestData['payment_amount'] ?? 0,
            'remaining_amount' => ($sale->total - $requestData['payment_amount']),
            'sale_id' => $sale->id
        ]);
        $customer_payment_histories = CustomerPaymentHistories::create([
            'debtor_user_id' => $debtor->id,
            'payment_date_id' => $paymentDate->id,
            'amount_paid' => $requestData['payment_amount'] ?? 0,
        ]);


        return $this->send_response(200, 'تم تغير حالة البيع بنجاح.', [], Sales::with(['sale_items'])->find($sale->id));
    }
    public function addInfoCustomer(Request $request)
    {
        $requestData = $request->json()->all();
        $validator = Validator::make($requestData, [
           'sale_id' => 'required|uuid|exists:sales,id',
           'name' => 'required|min:1',
        ], [
            'sale_id.required' => 'معرف العنصر مطلوب',
            'sale_id.exists' => 'العنصر غير موجود',
            'name.required' => 'اسم الزبون مطلوب',
            'name.min' => 'الحد الادنى لعدد الاحرف هوه 2',
        ]);


        $sale = Sales::find($requestData['sale_id']);
        $sale->update([
            'name' => $requestData['name'],
            'city' => $requestData['city'],
            'phone' => $requestData['phone'],
        ]);

        return $this->send_response(200, 'تم اضافة معلومات الزبون بنجاح.', [], Sales::with(['sale_items'])->find($sale->id));
    }
}
