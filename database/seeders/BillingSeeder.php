<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingPlan;
use App\Models\TenantSubscription;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Tenant;
use Carbon\Carbon;

class BillingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create billing plans
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small teams getting started',
                'monthly_price' => 29.99,
                'yearly_price' => 299.99,
                'currency' => 'USD',
                'features' => [
                    'Up to 5 users',
                    'Up to 10 projects',
                    '1GB storage',
                    'Email support',
                    'Basic analytics'
                ],
                'max_users' => 5,
                'max_projects' => 10,
                'storage_limit_mb' => 1024,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing businesses',
                'monthly_price' => 79.99,
                'yearly_price' => 799.99,
                'currency' => 'USD',
                'features' => [
                    'Up to 25 users',
                    'Unlimited projects',
                    '10GB storage',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                    'Custom integrations'
                ],
                'max_users' => 25,
                'max_projects' => null,
                'storage_limit_mb' => 10240,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with advanced needs',
                'monthly_price' => 199.99,
                'yearly_price' => 1999.99,
                'currency' => 'USD',
                'features' => [
                    'Unlimited users',
                    'Unlimited projects',
                    '100GB storage',
                    '24/7 phone support',
                    'Advanced analytics',
                    'Full API access',
                    'Custom integrations',
                    'SSO integration',
                    'Advanced security',
                    'Dedicated account manager'
                ],
                'max_users' => null,
                'max_projects' => null,
                'storage_limit_mb' => 102400,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            $planData['id'] = \Illuminate\Support\Str::ulid();
            BillingPlan::create($planData);
        }

        // Get created plans
        $basicPlan = BillingPlan::where('slug', 'basic')->first();
        $professionalPlan = BillingPlan::where('slug', 'professional')->first();
        $enterprisePlan = BillingPlan::where('slug', 'enterprise')->first();

        // Get existing tenants or create some
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $tenants = collect([
                Tenant::create([
                    'name' => 'Demo Company 1',
                    'slug' => 'demo-company-1',
                    'domain' => 'demo1.example.com',
                    'status' => 'active',
                    'is_active' => true,
                ]),
                Tenant::create([
                    'name' => 'Demo Company 2',
                    'slug' => 'demo-company-2',
                    'domain' => 'demo2.example.com',
                    'status' => 'active',
                    'is_active' => true,
                ]),
                Tenant::create([
                    'name' => 'Demo Company 3',
                    'slug' => 'demo-company-3',
                    'domain' => 'demo3.example.com',
                    'status' => 'active',
                    'is_active' => true,
                ]),
            ]);
        }

        // Create subscriptions
        $subscriptions = [];
        $plans = [$basicPlan, $professionalPlan, $enterprisePlan];
        $statuses = ['active', 'active', 'active', 'trial', 'canceled'];
        $billingCycles = ['monthly', 'yearly'];

        foreach ($tenants as $index => $tenant) {
            $plan = $plans[array_rand($plans)];
            $status = $statuses[array_rand($statuses)];
            $billingCycle = $billingCycles[array_rand($billingCycles)];
            
            $amount = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
            $startedAt = Carbon::now()->subDays(rand(1, 365));
            $renewAt = $startedAt->copy()->addMonth();
            
            if ($status === 'canceled') {
                $renewAt = null;
            }

            $subscription = TenantSubscription::create([
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'currency' => 'USD',
                'started_at' => $startedAt,
                'renew_at' => $renewAt,
                'canceled_at' => $status === 'canceled' ? $startedAt->copy()->addDays(rand(30, 300)) : null,
                'expires_at' => $status === 'canceled' ? $startedAt->copy()->addDays(rand(30, 300)) : null,
                'stripe_subscription_id' => 'sub_' . str_pad($index + 1, 10, '0', STR_PAD_LEFT),
                'stripe_customer_id' => 'cus_' . str_pad($index + 1, 10, '0', STR_PAD_LEFT),
            ]);

            $subscriptions[] = $subscription;
        }

        // Create additional subscriptions for more data
        for ($i = 0; $i < 20; $i++) {
            $tenant = $tenants->random();
            $plan = $plans[array_rand($plans)];
            $status = $statuses[array_rand($statuses)];
            $billingCycle = $billingCycles[array_rand($billingCycles)];
            
            $amount = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
            $startedAt = Carbon::now()->subDays(rand(1, 365));
            $renewAt = $startedAt->copy()->addMonth();
            
            if ($status === 'canceled') {
                $renewAt = null;
            }

            $subscription = TenantSubscription::create([
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'currency' => 'USD',
                'started_at' => $startedAt,
                'renew_at' => $renewAt,
                'canceled_at' => $status === 'canceled' ? $startedAt->copy()->addDays(rand(30, 300)) : null,
                'expires_at' => $status === 'canceled' ? $startedAt->copy()->addDays(rand(30, 300)) : null,
                'stripe_subscription_id' => 'sub_' . str_pad($i + 100, 10, '0', STR_PAD_LEFT),
                'stripe_customer_id' => 'cus_' . str_pad($i + 100, 10, '0', STR_PAD_LEFT),
            ]);

            $subscriptions[] = $subscription;
        }

        // Create invoices
        $invoiceStatuses = ['paid', 'unpaid', 'overdue'];
        
        foreach ($subscriptions as $subscription) {
            // Create 1-3 invoices per subscription
            $invoiceCount = rand(1, 3);
            
            for ($i = 0; $i < $invoiceCount; $i++) {
                $status = $invoiceStatuses[array_rand($invoiceStatuses)];
                $issueDate = $subscription->started_at->copy()->addMonths($i);
                $dueDate = $issueDate->copy()->addDays(30);
                $paidAt = $status === 'paid' ? $issueDate->copy()->addDays(rand(1, 15)) : null;
                
                if ($status === 'overdue') {
                    $dueDate = $issueDate->copy()->addDays(15); // Past due
                }

                $invoice = BillingInvoice::create([
                    'id' => \Illuminate\Support\Str::ulid(),
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'invoice_number' => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'description' => "Subscription payment for {$subscription->plan->name} plan",
                    'amount' => $subscription->amount,
                    'tax_amount' => $subscription->amount * 0.1, // 10% tax
                    'total_amount' => $subscription->amount * 1.1,
                    'currency' => 'USD',
                    'status' => $status,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'paid_at' => $paidAt,
                    'stripe_invoice_id' => 'in_' . str_pad(rand(100000, 999999), 10, '0', STR_PAD_LEFT),
                    'line_items' => [
                        [
                            'description' => "{$subscription->plan->name} Plan - {$subscription->billing_cycle}",
                            'amount' => $subscription->amount,
                            'quantity' => 1,
                        ]
                    ],
                ]);

                // Create payment for paid invoices
                if ($status === 'paid' && $paidAt) {
                    BillingPayment::create([
                        'id' => \Illuminate\Support\Str::ulid(),
                        'tenant_id' => $subscription->tenant_id,
                        'invoice_id' => $invoice->id,
                        'subscription_id' => $subscription->id,
                        'amount' => $invoice->total_amount,
                        'currency' => 'USD',
                        'status' => 'completed',
                        'payment_method' => 'stripe',
                        'payment_reference' => 'pi_' . str_pad(rand(100000, 999999), 10, '0', STR_PAD_LEFT),
                        'stripe_payment_intent_id' => 'pi_' . str_pad(rand(100000, 999999), 10, '0', STR_PAD_LEFT),
                        'processed_at' => $paidAt,
                    ]);
                }
            }
        }

        $this->command->info('Billing data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . BillingPlan::count() . ' billing plans');
        $this->command->info('- ' . TenantSubscription::count() . ' tenant subscriptions');
        $this->command->info('- ' . BillingInvoice::count() . ' billing invoices');
        $this->command->info('- ' . BillingPayment::count() . ' billing payments');
    }
}