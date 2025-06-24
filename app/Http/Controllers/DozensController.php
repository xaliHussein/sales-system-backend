<?php

namespace App\Http\Controllers;

use App\Models\Dozens;
use App\Models\Items;
use App\Traits\Filter;
use App\Traits\Search;
use App\Models\Product;
use App\Traits\OrderBy;
use App\Traits\Pagination;
use App\Traits\UploadImage;
use App\Traits\SendResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DozensController extends Controller
{
    use SendResponse;
    use UploadImage;
    use Pagination;
    use Search;
    use Filter;
    use OrderBy;

    public function getDozen()
    {
        $dozens = Dozens::where("user_id", auth()->user()->id)->with(['product', 'items']);

        if (isset($_GET["query"])) {
            $query = $_GET["query"];
            $dozens->where('barcode', '=', $query)->orWhereHas('product', function ($q) use ($query) {
                $q->where('name', 'like', "%$query%");
            });
        }

        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }
        $res = $this->paging($dozens, $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار المنتج بنجاح', [], $res["model"], null, $res["count"]);

    }

    public function addProductDozen(Request $request)
    {
        $requestData = $request->json()->all();

        $validator = Validator::make($requestData, [
            'barcode' => 'required|string|unique:dozens,barcode',
            'name' => 'required|string|max:255|min:3',
            'type' => 'required|in:0,1',
            'total_pieces' => 'required|integer|between:1,30', // 10 pieces per dozen
            'color' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:1000',
            'selling_price' => 'required|numeric|min:1000',
            'variants' => 'required|array',
            'variants.*.size' => 'required|string|max:3',
            'variants.*.quantity' => 'required|integer',


        ], [
            'name.required' => 'يرجى ادخال اسم الدزينة',
            'name.string' => 'اسم الدزينة يجب أن يكون نصاً',
            'name.max' => 'اسم الدزينة يجب أن لا يتجاوز 255 حرفاً',
            'name.min' => 'اسم الدزينة يجب أن يكون على الأقل 3 أحرف',
            'type.required' => 'يرجى تحديد نوع الدزينة',
            'type.in' => 'نوع الدزينة يجب أن يكون إما "ملابس" أو "احذيه"',
            'total_pieces.required' => 'يرجى تحديد عدد القطع في الدزينة',
            'total_pieces.integer' => 'عدد القطع يجب أن يكون رقماً صحيحاً',
            'total_pieces.between' => 'عدد القطع يجب أن يكون بين 1 و 30',
            'barcode.string' => 'الباركود يجب أن يكون نصاً',
            'barcode.max' => 'الباركود يجب أن لا يتجاوز 255 حرفاً',
            'barcode.required' => 'يرجى ادخال الباركود',
            'barcode.unique' => 'الباركود مستخدم من قبل',
            'color.required' => 'يرجى ادخال اللون',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقماً',
            'purchase_price.min' => 'سعر الشراء يجب أن يكون أكبر من أو يساوي 1000',
            'selling_price.numeric' => 'سعر البيع يجب أن يكون رقماً',
            'selling_price.min' => 'سعر البيع يجب أن يكون أكبر من أو يساوي 1000',
            'purchase_price.required' => 'يرجى ادخال سعر الشراء',
            'selling_price.required' => 'يرجى ادخال سعر البيع',
            'variants.required' => 'يرجى ادخال المقاسات',
            'variants.array' => 'المقاسات يجب أن تكون مصفوفة',
            'variants.*.size.required' => 'يرجى ادخال المقاس',
            'variants.*.size.string' => 'المقاس يجب أن يكون نصاً',
            'variants.*.size.max' => 'المقاس يجب أن لا يتجاوز 3 أحرف',
            'variants.*.quantity.required' => 'يرجى ادخال الكمية لكل مقاس',
            'variants.*.quantity.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
            'variants.*.quantity.max' => 'الكمية يجب أن لا تتجاوز 3',
        ]);

        if ($validator->fails()) {
            return $this->send_response(400, "خطأ في المدخلات", $validator->errors(), []);
        }


        $product = Product::create([
            'user_id' => auth()->user()->id,
            'name' => $requestData['name'],
            'type' => $requestData['type'],
        ]);

        $dozen = Dozens::create([
            'user_id' => auth()->user()->id,
            'product_id' => $product->id,
            'barcode' => $requestData['barcode'],
            'color' => $requestData['color'],
            'total_pieces' => $requestData['total_pieces'],
            'purchase_price' => $requestData['purchase_price'],
            'selling_price' => $requestData['selling_price'],
        ]);


        foreach ($requestData['variants'] as $variants) {
            Items::create([
                'user_id' => auth()->user()->id,
                'product_id' => $product->id,
                'dozen_id' => $dozen->id,
                'size' => $variants['size'],
                'quantity' => $variants['quantity'],
                'status' => 'available',
            ]);
        }

        return $this->send_response(200, 'تم إضافة المنتج بنجاح', [], Dozens::with(['product', 'items'])->find($dozen->id));
    }


    public function deleteDozenProduct(Request $request)
    {
        $requestData = $request->json()->all();

        $validator = Validator::make($requestData, [
             'id' => 'required|exists:products,id',
         ], [
             'id.required' => 'الرجاء ادخال معرف المنتج',
             'id.exists' => 'هذا المنتج غير موجود',
         ]);

        // Check if validation fails
        if ($validator->fails()) {
            return $this->send_response(400, 'خطأ في البيانات', $validator->errors());
        }

        $product = Product::find($requestData['id']);
        $dozen = Dozens::where('product_id', $product->id)->first();
        $items = Items::where('product_id', $product->id)->where('dozen_id', $dozen->id)->get();

        foreach ($items as $item) {
            $item->delete();
        }
        $dozen->delete();
        $product->delete();
        return $this->send_response(200, 'تم حذف المنتج بنجاح', [], []);

    }


    public function editDozenProduct(Request $request)
    {
        $requestData = $request->json()->all();

        $validator = Validator::make($requestData, [
            'id_product' => 'required|exists:products,id',
            'id_dozen' => 'required|exists:dozens,id',
            'barcode' => 'required|string|unique:dozens,barcode,' . $requestData['id_dozen'],
            'name' => 'required|string|max:255|min:3',
            'type' => 'required|in:0,1',
            'total_pieces' => 'required|integer|between:1,30', // 10 pieces per dozen
            'color' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:1000',
            'selling_price' => 'required|numeric|min:1000',
            'variants' => 'required|array',
            'variants.*.size' => 'required|string|max:3',
            'variants.*.quantity' => 'required|integer',

        ], [
            'id_product.required' => 'يرجى ادخال معرف المنتج',
            'id_product.exists' => 'هذا المنتج غير موجود',
            'id_dozen.required' => 'يرجى ادخال معرف الدزينة',
            'id_dozen.exists' => 'هذه الدزينة غير موجودة',
            'barcode.string' => 'الباركود يجب أن يكون نصاً',
            'barcode.max' => 'الباركود يجب أن لا يتجاوز 255 حرفاً',
            'barcode.required' => 'يرجى ادخال الباركود',
            'barcode.unique' => 'الباركود مستخدم من قبل',
            'name.required' => 'يرجى ادخال اسم الدزينة',
            'name.string' => 'اسم الدزينة يجب أن يكون نصاً',
            'name.max' => 'اسم الدزينة يجب أن لا يتجاوز 255 حرفاً',
            'name.min' => 'اسم الدزينة يجب أن يكون على الأقل 3 أحرف',
            'type.required' => 'يرجى تحديد نوع الدزينة',
            'type.in' => 'نوع الدزينة يجب أن يكون إما "ملابس" أو "احذيه"',
            'total_pieces.required' => 'يرجى تحديد عدد القطع في الدزينة',
            'total_pieces.integer' => 'عدد القطع يجب أن يكون رقماً صحيحاً',
            'total_pieces.between' => 'عدد القطع يجب أن يكون بين 1 و 30',
            'color.required' => 'يرجى ادخال اللون',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقماً',
            'purchase_price.min' => 'سعر الشراء يجب أن يكون أكبر من أو يساوي 1000',
            'selling_price.numeric' => 'سعر البيع يجب أن يكون رقماً',
            'selling_price.min' => 'سعر البيع يجب أن يكون أكبر من أو يساوي 1000',
            'purchase_price.required' => 'يرجى ادخال سعر الشراء',
            'selling_price.required' => 'يرجى ادخال سعر البيع',
            'variants.required' => 'يرجى ادخال المقاسات',
            'variants.array' => 'المقاسات يجب أن تكون مصفوفة',
            'variants.*.size.required' => 'يرجى ادخال المقاس',
            'variants.*.size.string' => 'المقاس يجب أن يكون نصاً',
            'variants.*.size.max' => 'المقاس يجب أن لا يتجاوز 3 أحرف',
            'variants.*.quantity.required' => 'يرجى ادخال الكمية لكل مقاس',
            'variants.*.quantity.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
            'variants.*.quantity.max' => 'الكمية يجب أن لا تتجاوز 3',

        ]);

        if ($validator->fails()) {
            return $this->send_response(400, "خطأ في المدخلات", $validator->errors(), []);
        }

        $product = Product::find($request["id_product"]);
        $product->update([
            'name' => $requestData['name'],
            'type' => $requestData['type'],
        ]);

        $dozen = Dozens::find($requestData['id_dozen']);
        $dozen->update([
            'barcode' => $requestData['barcode'],
            'color' => $requestData['color'],
            'purchase_price' => $requestData['purchase_price'],
            'selling_price' => $requestData['selling_price'],
            'total_pieces' => $requestData['total_pieces'],
        ]);

        $old_items = Items::where('product_id', $product->id)->where('dozen_id', $dozen->id)->get();
        $new_items = $requestData['variants'];

        $processed_ids = []; // collect updated or newly created item IDs

        foreach ($new_items as $item) {
            if (!empty($item['id'])) {
                // Update existing item
                $existing = Items::find($item['id']);
                if ($existing) {
                    $existing->update([
                        'size' => $item['size'],
                        'quantity' => $item['quantity'],
                        'status' => $item['status'],
                    ]);
                    $processed_ids[] = $existing->id;
                }
            } else {
                // Create new item
                $created = Items::create([
                    'user_id' => auth()->user()->id,
                    'product_id' => $product->id,
                    'dozen_id' => $dozen->id,
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                ]);
                $processed_ids[] = $created->id;
            }
        }

        // Delete items not present in the new list
        foreach ($old_items as $old_item) {
            if (!in_array($old_item->id, $processed_ids)) {
                $old_item->delete();
            }
        }

        return $this->send_response(200, "تم تعديل المنتج بنجاح", [], Dozens::with(['product', 'items'])->find($dozen->id));
    }

    public function getProductsByBarcode()
    {
        if($_GET['query'] != null || $_GET['query'] != "") {
            $dozen = Dozens::where("user_id", auth()->user()->id)->where('barcode', $_GET['query'])->with(['product', 'items'])->first();;
        } else {
            return $this->send_response(400, "الباركود مطلوب", [], []);
        }

        return $this->send_response(200, "تم العثور على المنتج بنجاح", [], $dozen);
    }
}
