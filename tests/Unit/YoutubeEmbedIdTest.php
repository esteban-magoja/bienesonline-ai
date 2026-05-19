<?php

use App\Models\PropertyListing;

it('extracts video ID from standard youtube.com/watch?v= URL', function () {
    $listing = new PropertyListing(['youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
    expect($listing->youtubeEmbedId())->toBe('dQw4w9WgXcQ');
});

it('extracts video ID from youtu.be short URL', function () {
    $listing = new PropertyListing(['youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    expect($listing->youtubeEmbedId())->toBe('dQw4w9WgXcQ');
});

it('extracts video ID from youtube shorts URL', function () {
    $listing = new PropertyListing(['youtube_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ']);
    expect($listing->youtubeEmbedId())->toBe('dQw4w9WgXcQ');
});

it('returns null when youtube_url is empty', function () {
    $listing = new PropertyListing(['youtube_url' => '']);
    expect($listing->youtubeEmbedId())->toBeNull();
});

it('returns null when youtube_url is null', function () {
    $listing = new PropertyListing();
    expect($listing->youtubeEmbedId())->toBeNull();
});

it('returns null for an invalid URL', function () {
    $listing = new PropertyListing(['youtube_url' => 'https://vimeo.com/123456']);
    expect($listing->youtubeEmbedId())->toBeNull();
});
