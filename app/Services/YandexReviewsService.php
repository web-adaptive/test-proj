<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class YandexReviewsService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);
    }

    public function fetchReviews($url)
    {
        try {
            Log::info("Fetching reviews from URL: {$url}");
            
            preg_match('/org\/([^\/]+)\/(\d+)/', $url, $matches);
            $orgId = $matches[2] ?? null;
            
            if (!$orgId) {
                throw new \Exception('Не удалось определить ID организации из URL');
            }
            
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Encoding' => 'gzip, deflate, br',
                ]
            ]);
            $html = $response->getBody()->getContents();
            
            Log::info("HTML length: " . strlen($html));
            
            try {
                Log::info("Trying DomCrawler parsing...");
                $reviews = $this->parseReviewsWithDomCrawler($html);
                if (!empty($reviews)) {
                    Log::info("DomCrawler found " . count($reviews) . " reviews");
                    return $reviews;
                }
            } catch (\Exception $e) {
                Log::warning("DomCrawler failed: " . $e->getMessage());
            }
            
            $apiUrl = "https://yandex.ru/maps/api/business/{$orgId}/reviews";
            try {
                $apiResponse = $this->client->get($apiUrl, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Referer' => $url,
                    ]
                ]);
                $apiData = json_decode($apiResponse->getBody()->getContents(), true);
                if ($apiData && isset($apiData['reviews'])) {
                    Log::info("Found reviews via API: " . count($apiData['reviews']));
                    return $this->parseReviewsFromJSON($apiData['reviews']);
                }
            } catch (\Exception $e) {
                Log::info("API request failed, trying HTML parsing: " . $e->getMessage());
            }
            
            return $this->parseReviews($html, $orgId);
        } catch (\Exception $e) {
            Log::error('Yandex reviews fetch error: ' . $e->getMessage());
            throw new \Exception('Не удалось получить отзывы: ' . $e->getMessage());
        }
    }

    protected function parseReviewsWithDomCrawler($html)
    {
        try {
            Log::info("Using Symfony DomCrawler to parse HTML");
            
            $crawler = new Crawler($html);
            $reviews = [];
            
            $selectors = [
                '[class*="business-review"]',
                '[class*="review"]',
                '[data-review-id]',
                'article[class*="review"]',
                'li[class*="review"]',
            ];
            
            $foundReviews = false;
            foreach ($selectors as $selector) {
                try {
                    $reviewNodes = $crawler->filter($selector);
                    if ($reviewNodes->count() > 0) {
                        Log::info("Found {$reviewNodes->count()} review nodes with selector: {$selector}");
                        $foundReviews = true;
                        
                        $reviewNodes->each(function (Crawler $node) use (&$reviews) {
                            try {
                                $text = '';
                                $textSelectors = [
                                    '[class*="review-text"]',
                                    '[class*="text"]',
                                    '[class*="comment"]',
                                    'p',
                                ];
                                
                                foreach ($textSelectors as $textSel) {
                                    $textNode = $node->filter($textSel);
                                    if ($textNode->count() > 0) {
                                        $text = trim($textNode->first()->text());
                                        if (strlen($text) > 20) break;
                                    }
                                }
                                
                                if (empty($text) || strlen($text) < 20) {
                                    $text = trim($node->text());
                                }
                                
                                $rating = 5;
                                $ratingNode = $node->filter('[class*="rating"], [class*="star"], [data-rating]');
                                if ($ratingNode->count() > 0) {
                                    $ratingText = $ratingNode->first()->attr('data-rating') 
                                        ?? $ratingNode->first()->text();
                                    if (preg_match('/(\d+)/', $ratingText, $match)) {
                                        $rating = (int)$match[1];
                                    }
                                    
                                    $stars = $node->filter('[class*="star"][class*="filled"], [class*="star"].active');
                                    if ($stars->count() > 0) {
                                        $rating = $stars->count();
                                    }
                                }
                                
                                $date = now();
                                $dateNode = $node->filter('time, [class*="date"], [datetime]');
                                if ($dateNode->count() > 0) {
                                    $dateText = $dateNode->first()->attr('datetime') 
                                        ?? $dateNode->first()->text();
                                    $parsed = strtotime($dateText);
                                    if ($parsed !== false) {
                                        $date = date('Y-m-d H:i:s', $parsed);
                                    }
                                }
                                
                                $authorName = '';
                                $authorNode = $node->filter('[class*="author"], [class*="reviewer"], a[href*="user"]');
                                if ($authorNode->count() > 0) {
                                    $authorName = trim($authorNode->first()->text());
                                    $authorName = preg_replace('/^(Автор|Author|От|From):\s*/i', '', $authorName);
                                }
                                
                                $phone = null;
                                $phoneNode = $node->filter('[href^="tel:"]');
                                if ($phoneNode->count() > 0) {
                                    $phone = str_replace('tel:', '', $phoneNode->first()->attr('href'));
                                }
                                
                                $text = trim($text);
                                $invalidPatterns = [
                                    '/^Подписаться/i',
                                    '/^Знаток города/i',
                                    '/^Дегустатор/i',
                                    '/^\d+ (января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)/i',
                                    '/^Посмотреть ответ/i',
                                ];
                                
                                $isInvalid = false;
                                foreach ($invalidPatterns as $pattern) {
                                    if (preg_match($pattern, $text)) {
                                        $isInvalid = true;
                                        break;
                                    }
                                }
                                
                                $authorName = trim($authorName);
                                $authorName = preg_replace('/Знаток города.*$/i', '', $authorName);
                                $authorName = preg_replace('/Дегустатор.*$/i', '', $authorName);
                                $authorName = preg_replace('/Подписаться.*$/i', '', $authorName);
                                $authorName = trim($authorName);
                                
                                if (!$isInvalid && !empty($text) && strlen($text) >= 20 && strlen($text) < 5000) {
                                    $reviews[] = [
                                        'external_id' => md5($text . $date . ($authorName ?: 'anonymous')),
                                        'date' => $date,
                                        'branch' => 'Филиал 1',
                                        'reviewer_name' => $authorName ?: 'Аноним',
                                        'reviewer_phone' => $phone,
                                        'rating' => $rating ?: 5,
                                        'text' => $text,
                                    ];
                                }
                            } catch (\Exception $e) {
                                Log::error('Error parsing review node with DomCrawler: ' . $e->getMessage());
                            }
                        });
                        
                        if (!empty($reviews)) {
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("Selector {$selector} failed: " . $e->getMessage());
                    continue;
                }
            }
            
            if (!$foundReviews) {
                Log::warning("DomCrawler: No review elements found with any selector");
            }
            
            Log::info("DomCrawler parsed " . count($reviews) . " reviews");
            return $reviews;
        } catch (\Exception $e) {
            Log::error('DomCrawler parse error: ' . $e->getMessage());
            return [];
        }
    }

    protected function parseReviews($html, $orgId = null)
    {
        $reviews = [];
        
        $jsonPatterns = [
            '/window\.__INITIAL_DATA__\s*=\s*({.*?});/s',
            '/window\.__INITIAL_STATE__\s*=\s*({.*?});/s',
            '/window\.__DATA__\s*=\s*({.*?});/s',
            '/"reviews"\s*:\s*\[(.*?)\]/s',
            '/<script[^>]*id="[^"]*state[^"]*"[^>]*>(.*?)<\/script>/is',
        ];
        
        foreach ($jsonPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $jsonStr = $matches[1];
                $jsonStr = preg_replace('/,\s*}/', '}', $jsonStr);
                $data = json_decode($jsonStr, true);
                if ($data) {
                    Log::info("Found JSON data with pattern: " . substr($pattern, 0, 50));
                    if (isset($data['reviews'])) {
                        $parsed = $this->parseReviewsFromJSON($data['reviews']);
                        if (!empty($parsed)) {
                            return $parsed;
                        }
                    }
                    if (isset($data['items'])) {
                        $parsed = $this->parseReviewsFromJSON($data['items']);
                        if (!empty($parsed)) {
                            return $parsed;
                        }
                    }
                    if (isset($data['data']['reviews'])) {
                        $parsed = $this->parseReviewsFromJSON($data['data']['reviews']);
                        if (!empty($parsed)) {
                            return $parsed;
                        }
                    }
                }
            }
        }
        
        preg_match_all('/<script[^>]*type="application\/json"[^>]*>(.*?)<\/script>/is', $html, $jsonMatches);
        
        foreach ($jsonMatches[1] as $jsonContent) {
            $data = json_decode($jsonContent, true);
            if ($data) {
                Log::info("Found JSON in script tag");
                if (isset($data['props']['pageProps']['reviews'])) {
                    return $this->parseReviewsFromJSON($data['props']['pageProps']['reviews']);
                }
                if (isset($data['reviews'])) {
                    return $this->parseReviewsFromJSON($data['reviews']);
                }
                if (isset($data['items'])) {
                    return $this->parseReviewsFromJSON($data['items']);
                }
            }
        }
        
        return $this->parseReviewsFromHTML($html);
    }

    protected function parseReviewsFromHTML($html)
    {
        $reviews = [];
        
        preg_match_all('/<script[^>]*type="application\/json"[^>]*>(.*?)<\/script>/is', $html, $jsonMatches);
        
        foreach ($jsonMatches[1] as $jsonContent) {
            $data = json_decode($jsonContent, true);
            if ($data) {
                if (isset($data['props']['pageProps']['reviews'])) {
                    return $this->parseReviewsFromJSON($data['props']['pageProps']['reviews']);
                }
                if (isset($data['reviews'])) {
                    return $this->parseReviewsFromJSON($data['reviews']);
                }
                if (isset($data['items'])) {
                    return $this->parseReviewsFromJSON($data['items']);
                }
            }
        }

        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/s', $html, $stateMatch)) {
            $data = json_decode($stateMatch[1], true);
            if ($data && isset($data['reviews'])) {
                return $this->parseReviewsFromJSON($data['reviews']);
            }
        }

        if (preg_match('/"reviews"\s*:\s*\[(.*?)\]/s', $html, $reviewsMatch)) {
            preg_match('/\{[^{}]*"reviews"\s*:\s*\[.*?\]\s*\}/s', $html, $fullMatch);
            if ($fullMatch) {
                $data = json_decode($fullMatch[0], true);
                if ($data && isset($data['reviews'])) {
                    return $this->parseReviewsFromJSON($data['reviews']);
                }
            }
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $selectors = [
            "//div[contains(@class, 'review')]",
            "//div[contains(@class, 'business-review')]",
            "//div[contains(@class, 'review-item')]",
            "//div[contains(@class, 'reviews-item')]",
            "//div[@data-review-id]",
            "//article[contains(@class, 'review')]",
            "//li[contains(@class, 'review')]",
        ];

        foreach ($selectors as $selector) {
            $reviewNodes = $xpath->query($selector);
            if ($reviewNodes->length > 0) {
                Log::info("Found {$reviewNodes->length} review nodes with selector: {$selector}");
                foreach ($reviewNodes as $node) {
                    $review = $this->parseReviewNodeImproved($node, $xpath);
                    if ($review && !empty($review['text'])) {
                        $reviews[] = $review;
                    }
                }
                if (!empty($reviews)) {
                    break;
                }
            }
        }

        if (empty($reviews)) {
            $reviews = $this->parseReviewsWithRegex($html);
        }
        
        if (empty($reviews)) {
            Log::warning("No reviews found, returning empty array. HTML snippet: " . substr($html, 0, 500));
        }

        Log::info("Parsed " . count($reviews) . " reviews from HTML");
        return $reviews;
    }

    protected function parseReviewsFromJSON($reviewsData)
    {
        $reviews = [];
        
        if (!is_array($reviewsData)) {
            return $reviews;
        }

        foreach ($reviewsData as $reviewData) {
            $text = $reviewData['text'] 
                ?? $reviewData['comment'] 
                ?? $reviewData['reviewText']
                ?? $reviewData['body']
                ?? $reviewData['content']
                ?? '';
            
            $authorName = $reviewData['author']['name'] 
                ?? $reviewData['authorName'] 
                ?? $reviewData['user']['name']
                ?? $reviewData['userName']
                ?? $reviewData['name']
                ?? '';
            
            $authorPhone = $reviewData['author']['phone'] 
                ?? $reviewData['authorPhone']
                ?? $reviewData['user']['phone']
                ?? $reviewData['phone']
                ?? null;
            
            $rating = $reviewData['rating'] 
                ?? $reviewData['stars']
                ?? $reviewData['score']
                ?? 5;
            
            $date = isset($reviewData['date']) 
                ? date('Y-m-d H:i:s', strtotime($reviewData['date']))
                : (isset($reviewData['createdAt']) 
                    ? date('Y-m-d H:i:s', strtotime($reviewData['createdAt']))
                    : now());
            
            $externalId = $reviewData['id'] 
                ?? $reviewData['reviewId']
                ?? md5($text . $date . $authorName);
            
            if (empty($text) || strlen($text) < 10) {
                continue;
            }
            
            if (empty($authorName) || strlen($authorName) < 2) {
                $authorName = 'Аноним';
            }

            $reviews[] = [
                'external_id' => $externalId,
                'date' => $date,
                'branch' => $reviewData['branch'] ?? 'Филиал 1',
                'reviewer_name' => $authorName,
                'reviewer_phone' => $authorPhone,
                'rating' => (int)$rating ?: 5,
                'text' => $text,
            ];
        }

        Log::info("Parsed " . count($reviews) . " reviews from JSON");
        return $reviews;
    }

    protected function parseReviewNode($node, $xpath)
    {
        return $this->parseReviewNodeImproved($node, $xpath);
    }

    protected function parseReviewNodeImproved($node, $xpath)
    {
        try {
            $text = '';
            $textSelectors = [
                ".//div[contains(@class, 'review-text')]",
                ".//p[contains(@class, 'review-text')]",
                ".//div[contains(@class, 'text')]",
                ".//div[contains(@class, 'comment')]",
                ".//div[@itemprop='reviewBody']",
                ".//p[@itemprop='reviewBody']",
                ".//div[contains(@class, 'review__text')]",
                ".//div[contains(@class, 'business-review__text')]",
            ];
            
            foreach ($textSelectors as $selector) {
                $textNodes = $xpath->query($selector, $node);
                if ($textNodes->length > 0) {
                    $text = trim($textNodes->item(0)->textContent);
                    if (!empty($text)) {
                        break;
                    }
                }
            }

            if (empty($text)) {
                $allText = trim($node->textContent);
                if (strlen($allText) > 20) {
                    $text = $allText;
                }
            }

            $rating = 5;
            $ratingSelectors = [
                ".//span[contains(@class, 'rating')]",
                ".//div[contains(@class, 'stars')]",
                ".//div[contains(@class, 'rating')]",
                ".//meta[@itemprop='ratingValue']",
                ".//span[@itemprop='ratingValue']",
            ];
            
            foreach ($ratingSelectors as $selector) {
                $ratingNodes = $xpath->query($selector, $node);
                if ($ratingNodes->length > 0) {
                    $ratingNode = $ratingNodes->item(0);
                    $ratingText = $ratingNode->getAttribute('content') 
                        ?? $ratingNode->getAttribute('data-rating')
                        ?? $ratingNode->getAttribute('data-value')
                        ?? $ratingNode->textContent;
                    
                    if (preg_match('/star[s\-]?(\d)/i', $ratingNode->getAttribute('class'), $starMatch)) {
                        $rating = (int)$starMatch[1];
                    } elseif (preg_match('/(\d+)/', $ratingText, $numMatch)) {
                        $rating = (int)$numMatch[1];
                    }
                    
                    if ($rating > 0 && $rating <= 5) {
                        break;
                    }
                }
            }

            $date = now();
            $dateSelectors = [
                ".//time",
                ".//span[contains(@class, 'date')]",
                ".//div[contains(@class, 'date')]",
                ".//meta[@itemprop='datePublished']",
                ".//time[@itemprop='datePublished']",
            ];
            
            foreach ($dateSelectors as $selector) {
                $dateNodes = $xpath->query($selector, $node);
                if ($dateNodes->length > 0) {
                    $dateNode = $dateNodes->item(0);
                    $dateText = $dateNode->getAttribute('datetime')
                        ?? $dateNode->getAttribute('content')
                        ?? $dateNode->getAttribute('data-date')
                        ?? $dateNode->textContent;
                    
                    if (!empty($dateText)) {
                        $parsedDate = strtotime($dateText);
                        if ($parsedDate !== false) {
                            $date = date('Y-m-d H:i:s', $parsedDate);
                            break;
                        }
                    }
                }
            }

            $authorName = '';
            $authorSelectors = [
                ".//span[contains(@class, 'author')]",
                ".//div[contains(@class, 'author-name')]",
                ".//a[contains(@class, 'author')]",
                ".//span[@itemprop='author']",
                ".//div[@itemprop='author']",
                ".//span[contains(@class, 'reviewer')]",
                ".//div[contains(@class, 'reviewer')]",
            ];
            
            foreach ($authorSelectors as $selector) {
                $authorNodes = $xpath->query($selector, $node);
                if ($authorNodes->length > 0) {
                    $authorName = trim($authorNodes->item(0)->textContent);
                    $authorName = preg_replace('/^(Автор|Author|От|From):\s*/i', '', $authorName);
                    if (!empty($authorName) && $authorName !== 'Аноним' && strlen($authorName) > 1) {
                        break;
                    }
                }
            }

            if (empty($authorName) || $authorName === 'Аноним') {
                $linkNodes = $xpath->query(".//a[contains(@href, 'user')]", $node);
                if ($linkNodes->length > 0) {
                    $authorName = trim($linkNodes->item(0)->textContent);
                }
            }

            $phone = null;
            $phoneNodes = $xpath->query(".//span[contains(@class, 'phone')] | .//a[contains(@href, 'tel:')]", $node);
            if ($phoneNodes->length > 0) {
                $phoneText = $phoneNodes->item(0)->getAttribute('href') ?? $phoneNodes->item(0)->textContent;
                if (preg_match('/(\+?\d[\d\s\-\(\)]{7,})/', $phoneText, $phoneMatch)) {
                    $phone = $phoneMatch[1];
                }
            }

            if (empty($text) || strlen($text) < 10) {
                return null;
            }

            if (empty($authorName)) {
                $authorName = 'Аноним';
            }

            return [
                'external_id' => md5($text . $date . $authorName),
                'date' => $date,
                'branch' => 'Филиал 1',
                'reviewer_name' => $authorName,
                'reviewer_phone' => $phone,
                'rating' => $rating ?: 5,
                'text' => $text,
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing review node: ' . $e->getMessage());
            return null;
        }
    }

    protected function parseReviewsWithRegex($html)
    {
        $reviews = [];
        
        if (preg_match_all('/data-review-id="([^"]+)"/', $html, $reviewIds)) {
            Log::info("Found " . count($reviewIds[1]) . " review IDs via regex");
            foreach ($reviewIds[1] as $reviewId) {
                if (preg_match('/data-review-id="' . preg_quote($reviewId, '/') . '".*?>(.*?)<\/div>/is', $html, $reviewMatch)) {
                    $reviewHtml = $reviewMatch[1];
                    if (preg_match('/(.{50,500})/s', $reviewHtml, $textMatch)) {
                        $text = strip_tags($textMatch[1]);
                        if (strlen($text) > 20) {
                            $reviews[] = [
                                'external_id' => $reviewId,
                                'date' => now(),
                                'branch' => 'Филиал 1',
                                'reviewer_name' => 'Аноним',
                                'reviewer_phone' => null,
                                'rating' => 5,
                                'text' => trim($text),
                            ];
                        }
                    }
                }
            }
        }
        
        return $reviews;
    }
}
