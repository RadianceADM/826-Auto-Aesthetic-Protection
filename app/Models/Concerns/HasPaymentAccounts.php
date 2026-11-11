<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * GCash and Maya accounts kept as columns ({method}_enabled, _qr, _name,
 * _number): the main ones in Payment settings, and a branch's own.
 *
 * @mixin Model
 */
trait HasPaymentAccounts
{
    /**
     * QR codes are kept as uploaded (no WebP conversion), so they always scan.
     */
    public static string $paymentDisk = 'public';

    public static function bootHasPaymentAccounts(): void
    {
        // A replaced or removed QR leaves no file behind
        static::saved(function (Model $model) {
            foreach (array_keys(self::paymentMethodLabels()) as $method) {
                $attribute = "{$method}_qr";
                $old = $model->getOriginal($attribute);

                if ($model->wasChanged($attribute) && $old && $old !== $model->{$attribute}) {
                    Storage::disk(self::$paymentDisk)->delete($old);
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethodLabels(): array
    {
        return [
            'gcash' => 'GCash',
            'maya' => 'Maya',
        ];
    }

    /**
     * The methods switched on that have something to pay to (a QR or a
     * number), in the order GCash, Maya.
     *
     * @return list<array{key: string, label: string, qr: string|null, accountName: string|null, accountNumber: string|null}>
     */
    public function methods(): array
    {
        $methods = [];

        foreach (self::paymentMethodLabels() as $key => $label) {
            $qr = $this->{"{$key}_qr"};
            $number = $this->{"{$key}_number"};

            if (! $this->{"{$key}_enabled"} || (blank($qr) && blank($number))) {
                continue;
            }

            $methods[] = [
                'key' => $key,
                'label' => $label,
                'qr' => $qr ? Storage::disk(self::$paymentDisk)->url($qr) : null,
                'accountName' => $this->{"{$key}_name"},
                'accountNumber' => $number,
            ];
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    public function methodKeys(): array
    {
        return array_column($this->methods(), 'key');
    }
}
