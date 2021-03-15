<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\Person;
use App\Models\Tenant\Item;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Series;
use App\Http\Requests\Tenant\PersonRequest;
use Modules\Item\Http\Requests\ItemRequest;


class MobileController extends Controller
{

    public function login(Request $request)
    {
        $credentials = request(['email', 'password']);
        if (!Auth::attempt($credentials)) {
            return [
                'success' => false,
                'message' => 'No Autorizado'
            ];
        }

        $company = Company::active();

        $user = $request->user();
        return [
            'success' => true,
            'name' => $user->name,
            'email' => $user->email,
            'token' => $user->api_token,
            'ruc' => $company->number,
            'logo' => $company->logo
        ];
    }

    public function customers()
    {
        $customers = Person::whereType('customers')->orderBy('name')->take(20)->get()->transform(function ($row) {
            return [
                'id' => $row->id,
                'description' => $row->number . ' - ' . $row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
                'identity_document_type_code' => $row->identity_document_type->code,
                'address' => $row->address,
                'telephone' => $row->telephone,
                'country_id' => $row->country_id,
                'district_id' => $row->district_id,
                'email' => $row->email,
                'selected' => false
            ];
        });

        return [
            'success' => true,
            'data' => array('customers' => $customers)
        ];
    }

    public function tables()
    {
        $affectation_igv_types = AffectationIgvType::whereActive()->get();
        /*$customers = Person::whereType('customers')->orderBy('name')->take(20)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
                'identity_document_type_code' => $row->identity_document_type->code
            ];
        });*/

        $items = Item::whereWarehouse()
            ->whereHasInternalId()
            ->whereNotIsSet()
            ->whereIsActive()
            ->orderBy('description')
            ->take(20)
            ->get()
            ->transform(function ($row) {
                $full_description = ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description;

                return [
                    'id' => $row->id,
                    'item_id' => $row->id,
                    'name' => $row->name,
                    'full_description' => $full_description,
                    'description' => $row->description,
                    'currency_type_id' => $row->currency_type_id,
                    'internal_id' => $row->internal_id,
                    'item_code' => $row->item_code,
                    'currency_type_symbol' => $row->currency_type->symbol,
                    'sale_unit_price' => number_format($row->sale_unit_price, 2),
                    'purchase_unit_price' => $row->purchase_unit_price,
                    'unit_type_id' => $row->unit_type_id,
                    'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                    'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                    'calculate_quantity' => (bool) $row->calculate_quantity,
                    'has_igv' => (bool) $row->has_igv,
                    'is_set' => (bool) $row->is_set,
                    'aux_quantity' => 1,

                ];
            });


        return [
            'success' => true,
            'data' => array('items' => $items, 'affectation_types' => $affectation_igv_types)
        ];
    }


    public function getSeries()
    {

        return Series::where('establishment_id', auth()->user()->establishment_id)
            ->whereIn('document_type_id', ['01', '03'])
            ->get()
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'document_type_id' => $row->document_type_id,
                    'number' => $row->number
                ];
            });
    }

    public function item(ItemRequest $request)
    {
        $row = new Item();
        $row->item_type_id = '01';
        $row->amount_plastic_bag_taxes = Configuration::firstOrFail()->amount_plastic_bag_taxes;
        $row->fill($request->all());
        $row->image = 'imagen-no-disponible.jpg';
        $row->save();

        $full_description = ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description;

        return [
            'success' => true,
            'msg' => 'Producto registrado con éxito',
            'data' => (object)[
                'id' => $row->id,
                'item_id' => $row->id,
                'name' => $row->name,
                'full_description' => $full_description,
                'description' => $row->description,
                'currency_type_id' => $row->currency_type_id,
                'internal_id' => $row->internal_id,
                'item_code' => $row->item_code,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => number_format($row->sale_unit_price, 2),
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'is_set' => (bool) $row->is_set,
                'aux_quantity' => 1,
            ],
        ];
    }

    public function person(PersonRequest $request)
    {
        $row = new Person();
        if ($request->department_id === '-') {
            $request->merge([
                'department_id' => null,
                'province_id'   => null,
                'district_id'   => null
            ]);
        }
        $row->fill($request->all());
        $row->save();

        return [
            'success' => true,
            'msg' => ($request->type == 'customers') ? 'Cliente registrado con éxito' : 'Proveedor registrado con éxito',
            'data' => (object)[
                'id' => $row->id,
                'description' => $row->number . ' - ' . $row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
                'identity_document_type_code' => $row->identity_document_type->code,
                'address' => $row->address,
                'email' => $row->email,
                'telephone' => $row->telephone,
                'country_id' => $row->country_id,
                'district_id' => $row->district_id,
                'selected' => false
            ]
        ];
    }

    public function searchItems(Request $request)
    {

        $items = Item::where('description', 'like', "%{$request->input}%")
            ->orWhere('internal_id', 'like', "%{$request->input}%")
            ->whereHasInternalId()
            ->whereWarehouse()
            ->whereNotIsSet()
            ->whereIsActive()
            ->orderBy('description')
            ->get()
            ->transform(function ($row) {

                $full_description = ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description;

                return [
                    'id' => $row->id,
                    'item_id' => $row->id,
                    'name' => $row->name,
                    'full_description' => $full_description,
                    'description' => $row->description,
                    'currency_type_id' => $row->currency_type_id,
                    'internal_id' => $row->internal_id,
                    'item_code' => $row->item_code,
                    'currency_type_symbol' => $row->currency_type->symbol,
                    'sale_unit_price' => number_format($row->sale_unit_price, 2),
                    'purchase_unit_price' => $row->purchase_unit_price,
                    'unit_type_id' => $row->unit_type_id,
                    'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                    'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                    'calculate_quantity' => (bool) $row->calculate_quantity,
                    'has_igv' => (bool) $row->has_igv,
                    'is_set' => (bool) $row->is_set,
                    'aux_quantity' => 1,
                    'warehouses' => collect($row->warehouses)->transform(function ($row) {
                        return [
                            'warehouse_description' => $row->warehouse->description,
                            'stock' => $row->stock,
                            'warehouse_id' => $row->warehouse_id,
                        ];
                    }),
                ];
            });

        return [
            'success' => true,
            'data' => array('items' => $items)
        ];
    }

    public function searchCustomers(Request $request)
    {

        $identity_document_type_id = $this->getIdentityDocumentTypeId($request->document_type_id);

        $customers = Person::where('name', 'like', "%{$request->input}%")
            ->orWhere('number', 'like', "%{$request->input}%")
            ->whereType('customers')
            ->whereIn('identity_document_type_id', $identity_document_type_id)
            ->orderBy('name')
            ->get()
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'description' => $row->number . ' - ' . $row->name,
                    'name' => $row->name,
                    'number' => $row->number,
                    'identity_document_type_id' => $row->identity_document_type_id,
                    'identity_document_type_code' => $row->identity_document_type->code,
                    'address' => $row->address,
                    'telephone' => $row->telephone,
                    'email' => $row->email,
                    'country_id' => $row->country_id,
                    'district_id' => $row->district_id,
                    'selected' => false
                ];
            });

        return [
            'success' => true,
            'data' => array('customers' => $customers)
        ];
    }


    public function getIdentityDocumentTypeId($document_type_id)
    {

        return ($document_type_id == '01') ? [6] : [1, 4, 6, 7, 0];
    }
}
