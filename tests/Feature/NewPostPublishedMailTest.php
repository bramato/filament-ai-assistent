<?php

use Bramato\FilamentStripeManageSubmissions\Exceptions\CannotSendEmail;
use Bramato\FilamentStripeManageSubmissions\Listeners\SendBlogPublishedNotification;
use Bramato\FilamentStripeManageSubmissions\Mails\BlogPublished;
use Bramato\FilamentStripeManageSubmissions\Models\NewsLetter;
use Bramato\FilamentStripeManageSubmissions\Models\Post;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->post = Post::factory()->published()->create();
});
it('check event listener is attached to the event', function () {
    // Arrange
    $post = Post::factory()->published()->create();

    // Assert
    Event::fake();
    event(new \Bramato\FilamentStripeManageSubmissions\Events\BlogPublished($post));

    Event::assertDispatched(\Bramato\FilamentStripeManageSubmissions\Events\BlogPublished::class);

    Event::assertListening(
        \Bramato\FilamentStripeManageSubmissions\Events\BlogPublished::class,
        SendBlogPublishedNotification::class
    );

});
it('send new post published email to news letter subscriber', function () {

    //Arrange
    $post = Post::factory()->published()->create();
    NewsLetter::factory()->count(3)->create();
    $subscribers = NewsLetter::all();

    Mail::fake();

    //Assert
    foreach ($subscribers as $subscriber) {
        Mail::send(new BlogPublished($post, $subscriber->email));
        Mail::assertSent(BlogPublished::class);

    }
});

it('includes post details on email template', function () {

    // Arrange
    $post = Post::factory()->published()->create();
    $subscriber = NewsLetter::factory()->create();
    $mail = new BlogPublished($post, $subscriber->email);

    //  Assert
    $mail->assertSeeInHtml('Thank you for subscribing to our blog updates!');
    $mail->assertSeeInHtml($post->title);
    $mail->assertSeeInHtml($post->featurePhoto);
    $mail->assertSeeInHtml('Read More');
    $mail->assertSeeInHtml(route('filamentblog.post.show', $post->slug));

});
it('throws exception if post is not published', function () {
    // Arrange
    $post = Post::factory()->create();
    $subscriber = NewsLetter::factory()->create();
    $mail = new BlogPublished($post, $subscriber->email);

    // Assert
    expect(fn () => $mail->envelope())->toThrow(CannotSendEmail::class);
});
