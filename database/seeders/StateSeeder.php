<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

/** Official GST state codes (the first two digits of every GSTIN). */
class StateSeeder extends Seeder
{
    public const STATES = [
        ['01', 'Jammu and Kashmir', true],
        ['02', 'Himachal Pradesh', false],
        ['03', 'Punjab', false],
        ['04', 'Chandigarh', true],
        ['05', 'Uttarakhand', false],
        ['06', 'Haryana', false],
        ['07', 'Delhi', true],
        ['08', 'Rajasthan', false],
        ['09', 'Uttar Pradesh', false],
        ['10', 'Bihar', false],
        ['11', 'Sikkim', false],
        ['12', 'Arunachal Pradesh', false],
        ['13', 'Nagaland', false],
        ['14', 'Manipur', false],
        ['15', 'Mizoram', false],
        ['16', 'Tripura', false],
        ['17', 'Meghalaya', false],
        ['18', 'Assam', false],
        ['19', 'West Bengal', false],
        ['20', 'Jharkhand', false],
        ['21', 'Odisha', false],
        ['22', 'Chhattisgarh', false],
        ['23', 'Madhya Pradesh', false],
        ['24', 'Gujarat', false],
        ['26', 'Dadra and Nagar Haveli and Daman and Diu', true],
        ['27', 'Maharashtra', false],
        ['29', 'Karnataka', false],
        ['30', 'Goa', false],
        ['31', 'Lakshadweep', true],
        ['32', 'Kerala', false],
        ['33', 'Tamil Nadu', false],
        ['34', 'Puducherry', true],
        ['35', 'Andaman and Nicobar Islands', true],
        ['36', 'Telangana', false],
        ['37', 'Andhra Pradesh', false],
        ['38', 'Ladakh', true],
        ['97', 'Other Territory', true],
        ['96', 'Foreign Country', false],
    ];

    public function run(): void
    {
        foreach (self::STATES as [$code, $name, $isUt]) {
            State::query()->updateOrCreate(['gst_code' => $code], ['name' => $name, 'is_union_territory' => $isUt]);
        }
    }
}
