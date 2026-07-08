<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
class HealthController extends Controller { public function __invoke(): JsonResponse { return response()->json(['name'=>config('app.name','Shree Axar ERP'),'status'=>'ok']); } }
