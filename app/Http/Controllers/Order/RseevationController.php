<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\Controller;

use App\Models\Rseevation;
use Illuminate\Http\Request;
use App\Http\Requests\Order\CreateResrvationRequest;
use App\Services\Order\RservationService;


class RseevationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $ReservationService;

    public function __construct(RservationService $ReservationService)
    {
        $this->ReservationService = $ReservationService;
    }

    public function createOrder(CreateResrvationRequest $request)
    {
        $order = $this->ReservationService->createOrder($request->validated());

        return response()->json($order, 201);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Rseevation $rseevation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rseevation $rseevation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rseevation $rseevation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rseevation $rseevation)
    {
        //
    }
}
