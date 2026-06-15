<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            [
                'name' => 'meta-llama/llama-3.3-70b-instruct:free',
                'display_name' => 'Llama 3.3 70B Instruct',
                'provider' => 'meta',
                'description' => 'Human-sounding chat interface for student wellness conversations',
                'is_active' => true,
                'max_tokens' => 131072,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['chat', 'streaming'],
            ],
            [
                'name' => 'qwen/qwen3-next-80b-a3b-thinking',
                'display_name' => 'Qwen3 Next 80B Thinking',
                'provider' => 'qwen',
                'description' => 'Core reasoning model for deeper app analysis',
                'is_active' => true,
                'max_tokens' => 262144,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['analysis', 'reasoning', 'multilingual'],
            ],
            [
                'name' => 'deepseek/deepseek-v4-pro',
                'display_name' => 'DeepSeek V4 Pro',
                'provider' => 'deepseek',
                'description' => 'Heavy analysis model for very large documents and long context tasks',
                'is_active' => true,
                'max_tokens' => 1048576,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['analysis', 'long_context'],
            ],
            [
                'name' => 'liquid/lfm-2.5-1.2b-thinking:free',
                'display_name' => 'LFM2.5 1.2B Thinking',
                'provider' => 'liquid',
                'description' => 'Fast fallback model for lightweight thinking tasks',
                'is_active' => true,
                'max_tokens' => 32768,
                'cost_per_input_token' => 0.000000,
                'cost_per_output_token' => 0.000000,
                'capabilities' => ['chat', 'reasoning', 'fallback'],
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
