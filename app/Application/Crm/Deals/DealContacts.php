<?php

namespace App\Application\Crm\Deals;

use App\Models\CrmDeal;
use App\Models\CrmDealContact;
use Illuminate\Validation\ValidationException;

class DealContacts
{
    /** @param  list<int>  $customerIds */
    public function sync(CrmDeal $deal, array $customerIds, ?int $primaryId): void
    {
        $customerIds = array_values(array_unique(array_map('intval', $customerIds)));

        if ($primaryId !== null && ! in_array($primaryId, $customerIds, true)) {
            throw ValidationException::withMessages([
                'customer' => 'مخاطب اصلی باید یکی از اشخاص معامله باشد.',
            ]);
        }

        if ($customerIds !== [] && $primaryId === null) {
            $primaryId = $customerIds[0];
        }

        if ($customerIds === []) {
            $deal->contacts()->delete();

            return;
        }

        $deal->contacts()->whereNotIn('customer_id', $customerIds)->delete();

        foreach ($customerIds as $customerId) {
            CrmDealContact::query()->updateOrCreate(
                [
                    'crm_deal_id' => $deal->id,
                    'customer_id' => $customerId,
                ],
                [
                    'organization_id' => $deal->organization_id,
                    'is_primary' => $customerId === $primaryId,
                ],
            );
        }

        CrmDealContact::query()
            ->where('crm_deal_id', $deal->id)
            ->where('customer_id', '!=', $primaryId)
            ->update(['is_primary' => false]);
    }
}
