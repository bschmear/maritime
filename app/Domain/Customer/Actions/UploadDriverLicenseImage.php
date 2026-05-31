<?php

declare(strict_types=1);

namespace App\Domain\Customer\Actions;

use App\Actions\PublicStorage;
use App\Domain\Customer\Models\Customer;
use App\Domain\InventoryImage\Models\InventoryImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UploadDriverLicenseImage
{
    private const SIDE_FRONT = 'front';

    private const SIDE_BACK = 'back';

    public function __construct(
        private readonly PublicStorage $publicStorage,
    ) {}

    /**
     * @return array{side: string, image: InventoryImage}
     */
    public function __invoke(Customer $customer, UploadedFile $file, string $side): array
    {
        $side = strtolower($side);
        if (! in_array($side, [self::SIDE_FRONT, self::SIDE_BACK], true)) {
            throw ValidationException::withMessages([
                'side' => 'Side must be front or back.',
            ]);
        }

        $column = $side === self::SIDE_FRONT ? 'dl_front_id' : 'dl_back_id';
        $role = $side === self::SIDE_FRONT ? 'dl_front' : 'dl_back';
        $displayName = $side === self::SIDE_FRONT ? 'Driver license (front)' : 'Driver license (back)';

        return DB::transaction(function () use ($customer, $file, $column, $role, $displayName, $side) {
            $previousId = $customer->{$column};

            $upload = $this->publicStorage->store(
                file: $file,
                directory: 'customer_profiles/driver-licenses',
                resizeWidth: 2000,
                existingFile: null,
                crop: false,
                deleteOld: false,
                isPrivate: false,
            );

            $image = InventoryImage::query()->create([
                'imageable_type' => Customer::class,
                'imageable_id' => $customer->id,
                'display_name' => $displayName,
                'file' => $upload['key'],
                'file_extension' => $upload['file_extension'],
                'file_size' => $upload['file_size'],
                'sort_order' => 0,
                'role' => $role,
                'is_primary' => false,
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
            ]);

            $customer->forceFill([$column => $image->id])->save();

            if ($previousId && (int) $previousId !== (int) $image->id) {
                InventoryImage::query()->whereKey($previousId)->delete();
            }

            return [
                'side' => $side,
                'image' => $image->fresh(),
            ];
        });
    }
}
