<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Factories\TestDataFactory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;

/**
 * Test Data Seeder
 * 
 * Seeds consistent test data for all test cases
 */
class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding test data...');

        // Create realistic test scenario
        $realisticData = TestDataFactory::createRealisticData();
        
        $this->command->info('✅ Created realistic test data:');
        $this->command->info("   - Tenant: {$realisticData['tenant']->name}");
        $this->command->info("   - Users: " . count($realisticData['users']));
        $this->command->info("   - Project: {$realisticData['project']->name}");
        $this->command->info("   - Tasks: " . count($realisticData['tasks']));
        $this->command->info("   - Client: {$realisticData['client']->name}");
        $this->command->info("   - Quote: {$realisticData['quote']->title}");

        // Create additional test scenarios
        $this->createAdditionalScenarios();

        // Create multi-tenant scenario
        $multiTenantData = TestDataFactory::createMultiTenantScenario();
        
        $this->command->info('✅ Created multi-tenant test data:');
        foreach ($multiTenantData as $key => $scenario) {
            $this->command->info("   - {$key}: {$scenario['tenant']->name}");
        }

        // Display statistics
        $stats = TestDataFactory::getStats();
        $this->command->info('📊 Test data statistics:');
        $this->command->info("   - Tenants: {$stats['tenants']}");
        $this->command->info("   - Users: {$stats['users']}");
        $this->command->info("   - Projects: {$stats['projects']}");
        $this->command->info("   - Tasks: {$stats['tasks']}");
        $this->command->info("   - Clients: {$stats['clients']}");
        $this->command->info("   - Quotes: {$stats['quotes']}");

        $this->command->info('🎉 Test data seeding completed!');
    }

    /**
     * Create additional test scenarios
     */
    private function createAdditionalScenarios(): void
    {
        // Scenario 1: Small company with minimal data
        $smallCompany = TestDataFactory::createTenant([
            'name' => 'Small Startup',
            'domain' => 'small-startup.com',
        ]);

        $founder = TestDataFactory::createUser($smallCompany, [
            'name' => 'Alex Founder',
            'email' => 'alex@small-startup.com',
            'role' => 'pm',
        ]);

        $project = TestDataFactory::createProject($smallCompany, $founder, [
            'name' => 'MVP Development',
            'status' => 'active',
            'budget_total' => 50000.00,
        ]);

        TestDataFactory::createTask($project, $founder, [
            'title' => 'Setup Development Environment',
            'status' => 'completed',
        ]);

        // Scenario 2: Large company with complex data
        $largeCompany = TestDataFactory::createTenant([
            'name' => 'Enterprise Corp',
            'domain' => 'enterprise-corp.com',
        ]);

        $ceo = TestDataFactory::createUser($largeCompany, [
            'name' => 'CEO Enterprise',
            'email' => 'ceo@enterprise-corp.com',
            'role' => 'pm',
        ]);

        $manager = TestDataFactory::createUser($largeCompany, [
            'name' => 'Manager Enterprise',
            'email' => 'manager@enterprise-corp.com',
            'role' => 'member',
        ]);

        $employee = TestDataFactory::createUser($largeCompany, [
            'name' => 'Employee Enterprise',
            'email' => 'employee@enterprise-corp.com',
            'role' => 'member',
        ]);

        // Create multiple projects
        $projects = [
            TestDataFactory::createProject($largeCompany, $ceo, [
                'name' => 'Digital Transformation',
                'status' => 'active',
                'priority' => 'high',
                'budget_total' => 1000000.00,
            ]),
            TestDataFactory::createProject($largeCompany, $manager, [
                'name' => 'Process Optimization',
                'status' => 'planning',
                'priority' => 'medium',
                'budget_total' => 250000.00,
            ]),
            TestDataFactory::createProject($largeCompany, $employee, [
                'name' => 'Legacy System Migration',
                'status' => 'completed',
                'priority' => 'low',
                'budget_total' => 500000.00,
            ]),
        ];

        // Create tasks for each project
        foreach ($projects as $project) {
            TestDataFactory::createTask($project, $manager, [
                'title' => 'Project Planning',
                'status' => 'completed',
            ]);
            
            TestDataFactory::createTask($project, $employee, [
                'title' => 'Implementation',
                'status' => $project->status === 'completed' ? 'completed' : 'pending',
            ]);
        }

        // Create clients and quotes
        $client = TestDataFactory::createClient($largeCompany, [
            'name' => 'Big Client Corp',
            'company' => 'Big Client Corporation',
        ]);

        TestDataFactory::createQuote($largeCompany, $client, [
            'title' => 'Enterprise Service Package',
            'total_amount' => 2000000.00,
            'status' => 'accepted',
        ]);

        $this->command->info('✅ Created additional test scenarios');
    }
}