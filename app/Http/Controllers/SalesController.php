<?php

namespace App\Http\Controllers;

use App\Models\Items;
use App\Models\Sales;
use App\Models\Dozens;
use App\Traits\Filter;
use App\Traits\Search;
use App\Models\Product;
use App\Traits\OrderBy;
use App\Models\Sale_items;
use App\Traits\Pagination;
use App\Traits\UploadImage;
use App\Traits\SendResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

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
        $sales = Sales::where("user_id", auth()->user()->id);
        if (isset($_GET["query"])) {
            $sales = $this->search($sales, 'sales');
        }
        if (isset($_GET["filter"])) {
            $filter = json_decode($_GET["filter"]);
            $sales = $this->filter($sales, $_GET["filter"]);
        }

        if (isset($_GET["order_by"])) {
            $sales = $this->order_by($sales, $_GET);
        }
        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }

        $res = $this->paging($sales->orderBy("created_at", "DESC"), $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار جميع المنتجات', [], $res["model"], null, $res["count"]);
    }

    public function addSales(Request $request)
    {
        $requestData = $request->json()->all();

        $validator = Validator::make($requestData, [
            'variants' => 'required|array',
            'variants.*.id' => 'required|exists:items,id',
            'variants.*.quantity' => 'required|integer|min:1',
            'variants.*.dozen_id' => 'required|exists:dozens,id',
        ], [
            'variants.required' => 'معرف المتغيرات مطلوب',
            'variants.array' => 'المتغيرات يجب أن تكون مصفوفة',
            'variants.*.id.required' => 'معرف العنصر مطلوب',
            'variants.*.id.exists' => 'العنصر غير موجود',
            'variants.*.quantity.required' => 'الكمية مطلوبة',
            'variants.*.quantity.integer' => 'الكمية يجب أن تكون عدد صحيح',
            'variants.*.quantity.min' => 'الكمية يجب أن تكون أكبر من أو تساوي 1',
            'variants.*.dozen_id.required' => 'معرف الدزينة مطلوب',
            'variants.*.dozen_id.exists' => 'الدزينة غير موجودة',
        ]);

        if ($validator->fails()) {
            return $this->send_response(400, "خطأ في المدخلات", $validator->errors(), []);
        }

        $total_price = 0;
        $data = [];

        // عملية حساب السعر الإجمالي
        $sales_items_data = [];
        foreach ($requestData['variants'] as $variant) {
            $item = Items::find($variant['id']);
            $dozen = Dozens::where('id', $variant['dozen_id'])->where('user_id', auth()->user()->id)->first();
            if ($item->quantity < $variant['quantity']) {
                return $this->send_response(400, 'الكمية المطلوبة أكبر من المتاحة لاحد العناصر', [], []);
            }


            $quantity = $item->quantity - $variant['quantity'];
            $data_item = [];
            $data_item['quantity'] = $quantity;
            if ($quantity == 0) {
                $data_item['status'] = 'sold_out';
            }
            $item->update($data_item);

            $total_price += $dozen->selling_price * $variant['quantity'];
            // خزن بيانات البيع
            $sales_items_data[] = [
                'item_id' => $variant['id'],
                'dozen_price' => $dozen->selling_price,
                'quantity' => $variant['quantity'],
            ];
        }

        $sales = Sales::create([
            'user_id' => auth()->user()->id,
            'invoice_number' => $this->random_code(),
            'total' => $total_price,
        ]);

        foreach ($sales_items_data as $item_data) {
            Sale_items::create([
                'user_id' => auth()->user()->id,
                'sale_id' => $sales->id,
                'item_id' => $item_data['item_id'],
                'price' => $item_data['dozen_price'],
                'quantity' => $item_data['quantity'],
            ]);
        }

        return $this->send_response(200, 'تمت العملية بنجاح', [], $sales);
    }
}
