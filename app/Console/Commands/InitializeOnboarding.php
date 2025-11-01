<?php

namespace App\Console\Commands;

use App\Services\OnboardingService;
use Illuminate\Console\Command;

class InitializeOnboarding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onboarding:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize default onboarding steps';

    protected $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        parent::__construct();
        $this->onboardingService = $onboardingService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Initializing onboarding steps...');
        
        try {
            $this->onboardingService->createDefaultSteps();
            $this->info('✅ Default onboarding steps created successfully!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to create onboarding steps: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
