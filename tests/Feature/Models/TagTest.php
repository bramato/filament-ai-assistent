<?php

use Bramato\FilamentStripeManageSubmissions\Models\Post;
use Bramato\FilamentStripeManageSubmissions\Models\Tag;

it('has posts', function () {
    // Arrange
    $tag = Tag::factory()
        ->hasAttached(Post::factory()->count(3))
        ->create();

    // Act & Assert
    expect($tag->posts)
        ->toHaveCount(3)
        ->each
        ->toBeInstanceOf(Post::class);
});
