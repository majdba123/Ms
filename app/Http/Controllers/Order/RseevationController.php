<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\Controller;

use App\Models\Rseevation;
use Illuminate\Http\Request;
use App\Http\Requests\Order\CreateResrvationRequest;
use App\Services\Order\RservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
  public function getUserReservations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:all,pending,complete,cancelled',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return response()->json(['error' => $errors], 422);
        }

        $user = Auth::user();
        $status = $request->status;

        if ($status === 'all') {
            $reservations = Rseevation::where('user_id', $user->id)
                                        ->with('product')
                                        ->get();
        } else {
            $reservations = Rseevation::where('user_id', $user->id)
                            ->where('status', $status)
                            ->with('product')
                            ->get();
        }

        return response()->json(['reservations' => $reservations], 200);
    }


    public function getProductReservation($reservation_id)
    {
        $user = Auth::user();

        // جلب الحجز والتحقق من أن المستخدم هو صاحب الحجز
        $reservation = Rseevation::where('id', $reservation_id)
                      ->where('user_id', $user->id)
                      ->with('product')
                      ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or you do not have permission to view this reservation'], 404);
        }

        return response()->json(['reservation' => $reservation], 200);
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
