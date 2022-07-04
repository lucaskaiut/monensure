<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillResource;
use App\Http\Resources\ListBillResource;
use App\Http\Responses;
use App\Http\Validators\BillValidator;
use App\Services\BillService;
use App\Traits\CoreController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BillController extends Controller
{
    use CoreController;

    public function __construct()
    {
        $this->service = app(BillService::class);
        $this->resource = BillResource::class;
        $this->listResource = ListBillResource::class;
        $this->requestValidator = new BillValidator();

        $this->authorizeResource($this->service->model, 'id');
    }

    public function index()
    {
        $response = $this->service->paginate(request()->query('per_page'));

        $content = $this->listResource::collection($response['bills']);

        return Responses::ok($content, ['total' => $response['total']]);
    }

    public function pay($id)
    {
        return DB::transaction(function() use ($id) {
            $bill = $this->service->pay($id);

            $content = new $this->resource($bill);

            return Responses::updated($content);
        });
    }

    public function payBills(Request $request)
    {
        $this->service->payBills($request->bills);

        return Responses::updated([]);
    }
}
