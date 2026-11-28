<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

/**
 * IT service desk categories (report section 4.3).
 */
class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Hardware', 'Computers, printers, monitors, peripherals and equipment faults.'],
            ['Software', 'Installation, application errors, licences and software support.'],
            ['Network and Internet', 'Wi-Fi, LAN, connectivity and internet access.'],
            ['User Accounts', 'Account creation, password resets and account problems.'],
            ['Systems and Applications', 'CRM, databases, internal systems and business applications.'],
            ['Access Requests', 'Access to folders, systems, services or resources.'],
            ['Maintenance', 'Preventive maintenance, repairs and servicing.'],
            ['New Equipment', 'New or replacement IT equipment.'],
            ['Other', 'IT requests not covered above.'],
        ];

        foreach ($categories as [$name, $description]) {
            TicketCategory::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true],
            );
        }
    }
}
