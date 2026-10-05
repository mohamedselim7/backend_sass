<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'free', 'name' => 'مجاني', 'price' => 0, 'monthly_credits' => 20, 'sort_order' => 0,
                'features' => ['براند واحد', 'خطة محتوى شهرية']],
            ['code' => 'starter', 'name' => 'ستارتر', 'price' => 499, 'monthly_credits' => 300, 'sort_order' => 1,
                'features' => ['3 براندات', 'تصاميم AI', 'جدولة النشر']],
            ['code' => 'pro', 'name' => 'برو', 'price' => 1299, 'monthly_credits' => 1000, 'sort_order' => 2,
                'features' => ['براندات غير محدودة', 'أولوية الدعم', 'تقارير متقدمة']],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan + [
                'currency' => config('payments.currency', 'EGP'),
                'interval' => 'month',
                'is_active' => true,
            ]);
        }
    }
}
