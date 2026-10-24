<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Platform\Enums\AdminArea;
use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\BillingInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintBillingInvoiceController extends Controller
{
    public function __invoke(Request $request, BillingInvoice $invoice): View
    {
        abort_unless($request->user()?->hasAdminArea(AdminArea::Finance), 403);

        $invoice->load(['lines', 'organization', 'payments']);

        return view('admin.billing-invoice-print', [
            'invoice' => $invoice,
            'seller' => (array) AdminSetting::valueFor(AdminSetting::SELLER_PROFILE, []),
            'buyer' => $invoice->buyer ?? [],
        ]);
    }
}
