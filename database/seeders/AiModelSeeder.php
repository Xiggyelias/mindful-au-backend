<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AiModel;

class AiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            [
                'name' => 'nvidia/nemotron-nano-9b-v2:free',
                'display_name' => 'NVIDIA Nemotron Nano 9B',
                'provider' => 'nvidia',
                'description' => 'A small, efficient model for general chat tasks',
                'is_active' => true,
                'max_tokens' => 4096,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['chat', 'streaming'],
            ],
            [
                'name' => 'qwen/qwen3-4b:free',
                'display_name' => 'Qwen 3 4B',
                'provider' => 'qwen',
                'description' => 'A 4B parameter model for conversational AI',
                'is_active' => true,
                'max_tokens' => 8192,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['chat', 'streaming', 'multilingual'],
            ],
            [
                'name' => 'openai/gpt-3.5-turbo',
                'display_name' => 'GPT-3.5 Turbo',
                'provider' => 'openai',
                'description' => 'Fast and efficient model for general tasks',
                'is_active' => false, // Not free tier
                'max_tokens' => 4096,
                'cost_per_input_token' => 0.000001,
                'cost_per_output_token' => 0.000002,
                'capabilities' => ['chat', 'streaming', 'function_calling'],
            ],
            [
                'name' => 'anthropic/claude-3-haiku',
                'display_name' => 'Claude 3 Haiku',
                'provider' => 'anthropic',
                'description' => 'Fast and efficient model for quick responses',
                'is_active' => false, // Not free tier
                'max_tokens' => 100000,
                'cost_per_input_token' => 0.00000025,
                'cost_per_output_token' => 0.00000125,
                'capabilities' => ['chat', 'streaming', 'analysis'],
            ],
        ];

        foreach ($models as $model) {
            AiModel::updateOrCreate(
                ['name' => $model['name']],
                $model
            );
        }
    }
}
