<?php

namespace App\Console\Commands;

use App\Services\YandexReviewsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestYandexParsing extends Command
{
    protected $signature = 'test:yandex-parsing {url}';
    protected $description = 'Test Yandex Maps reviews parsing';

    public function handle(YandexReviewsService $service)
    {
        $url = $this->argument('url');
        
        $this->info("Testing Yandex parsing for URL: {$url}");
        $this->info("==========================================");
        
        try {
            $reviews = $service->fetchReviews($url);
            
            $this->info("\nFound " . count($reviews) . " reviews:");
            $this->info("==========================================\n");
            
            foreach ($reviews as $index => $review) {
                $this->line("Review #" . ($index + 1) . ":");
                $this->line("  Date: " . $review['date']);
                $this->line("  Author: " . $review['reviewer_name']);
                $this->line("  Phone: " . ($review['reviewer_phone'] ?? 'N/A'));
                $this->line("  Rating: " . $review['rating'] . " stars");
                $this->line("  Branch: " . $review['branch']);
                $this->line("  Text: " . substr($review['text'], 0, 100) . "...");
                $this->line("  External ID: " . $review['external_id']);
                $this->line("");
            }
            
            if (empty($reviews)) {
                $this->warn("No reviews found!");
                $this->info("Check logs: storage/logs/laravel.log");
            } else {
                $this->info("✅ Successfully parsed " . count($reviews) . " reviews!");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->info("Check logs: storage/logs/laravel.log");
            return 1;
        }
    }
}
