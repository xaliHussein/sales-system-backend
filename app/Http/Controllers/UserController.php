<?php

namespace App\Http\Controllers;

use App\Models\User;
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

class UserController extends Controller
{
    use SendResponse;
    use UploadImage;
    use Pagination;
    use Search;
    use Filter;
    use OrderBy;

    public function getUsers()
    {
        $users = User::select("*");
        if (isset($_GET["query"])) {
            $users = $this->search($users, 'users');
        }
        if (isset($_GET["filter"])) {
            $filter = json_decode($_GET["filter"]);
            $users = $this->filter($users, $_GET["filter"]);
        }

        if (isset($_GET["order_by"])) {
            $users = $this->order_by($users, $_GET);
        }
        if (!isset($_GET['skip'])) {
            $_GET['skip'] = 0;
        }
        if (!isset($_GET['limit'])) {
            $_GET['limit'] = 10;
        }

        $res = $this->paging($users->orderBy("created_at", "DESC"), $_GET['skip'], $_GET['limit']);
        return $this->send_response(200, 'تم احضار جميع المستخدمين', [], $res["model"], null, $res["count"]);
    }

    public function login(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'email' => 'required|exists:users,email',
            'password' => 'required'
        ], [
            'email.required' => 'يرجى ادخال اسم المستخدم ',
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا',
            'password.required' => 'يرجى ادخال كلمة المرور ',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        if (auth()->attempt(array('email' => $request['email'], 'password' => $request['password']))) {
            $user = auth()->user();
            if ($user->account_status == 1) {
                $token = $user->createToken('sales_system')->accessToken;
                return $this->send_response(200, 'تم تسجيل الدخول بنجاح', [], $user, $token);
            } elseif ($user->account_status == 0) {
                return $this->send_response(400, 'تم حظر حسابك يرجى التواصل مع المالك', [], null, null);
            }
        } else {
            return $this->send_response(400, "ادخلت اسم مستخدم او كلمة مرور غير صحيحة", [], null, null);
        }
    }

    public function addUsers(Request $request)
    {
        $request = $request->json()->all();
        $validator = Validator::make($request, [
            'email' => 'required|unique:users,email',
            "name" => "required|string|max:255|min:3",
            "password" => "required|string|max:255|min:8",


        ], [
            'name.required' => 'يرجى ادخال اسم العميل',
            'email.required' => 'يرجى ادخال البريد الالكتروني ',
            'email.unique' => 'البريد الالكتروني مستخدم بالفعل',
            'password.required' => 'يرجى ادخال كلمة المرور ',
        ]);
        if ($validator->fails()) {
            return $this->send_response(400, "حصل خطأ في المدخلات", $validator->errors(), []);
        }

        $data = [
            'name' => $request['name'],
            'email' => $request['email'],
            'password' => bcrypt($request['password']),
            'account_status' => 1,
            'user_type' => 1,
        ];
        $user = User::create($data);
        return $this->send_response(200, 'تم إضافة المستخدم بنجاح', [], User::find($user->id));
    }
}
