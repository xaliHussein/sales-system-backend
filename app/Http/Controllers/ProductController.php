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

class ProductController extends Controller
{
    use SendResponse;
    use UploadImage;
    use Pagination;
    use Search;
    use Filter;
    use OrderBy;



}
