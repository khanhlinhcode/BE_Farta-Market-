<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()
                ->addresses()
                ->orderByDesc('is_default')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $user = $request->user();

        $address = DB::transaction(function () use ($user, $data) {
            if (($data['is_default'] ?? false) || ! $user->addresses()->exists()) {
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $user->addresses()->create($data);
        });

        return response()->json([
            'data' => $address,
        ], 201);
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $this->validatedData($request);

        $address = DB::transaction(function () use ($request, $address, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->refresh();
        });

        return response()->json([
            'data' => $address,
        ]);
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);
        $address->delete();

        return response()->noContent();
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        $address = DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->refresh();
        });

        return response()->json([
            'data' => $address,
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
            'address_line' => ['required', 'string', 'min:10', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
