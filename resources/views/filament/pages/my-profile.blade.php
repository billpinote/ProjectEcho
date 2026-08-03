<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <form wire:submit.prevent="save" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                        <input wire:model.defer="first_name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Middle Name</label>
                        <input wire:model.defer="middle_name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                        <input wire:model.defer="last_name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input wire:model.defer="email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Operator (OPR)</label>
                        <input wire:model.defer="operator" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Ratings</label>
                        <input wire:model.defer="ratings" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">License Number</label>
                        <input wire:model.defer="license_number" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">License Expiry Date</label>
                        <input wire:model.defer="license_expiry_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Medical Expiry Date</label>
                        <input wire:model.defer="medical_expiry_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea wire:model.defer="remarks" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-primary-600 focus:outline-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Save Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
