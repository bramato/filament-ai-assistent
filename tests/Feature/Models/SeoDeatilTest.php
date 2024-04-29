<?php

use Bramato\FilamentStripeManageSubmissions\Models\Post;
use Bramato\FilamentStripeManageSubmissions\Models\SeoDetail;

it('belongs to post', function () {
    // Arrange
    $post = Post::factory()->has(SeoDetail::factory())->create();

    // Act & Assert
    expect($post->seoDetail)->toBeInstanceOf(SeoDetail::class);
});
