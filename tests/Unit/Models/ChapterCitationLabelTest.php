<?php

use App\Models\Chapter;

it('labels numbered chapters as Ch. N', function () {
    $chapter = new Chapter(['number' => 12, 'title' => 'Enterprise Patterns']);

    expect($chapter->citationLabel())->toBe('Ch. 12');
});

it('labels appendix chapters as App. X', function () {
    $a = new Chapter(['number' => 23, 'title' => 'Appendix A: Bibliography']);
    $b = new Chapter(['number' => 24, 'title' => 'Appendix B: A Simple Parser']);

    expect($a->citationLabel())->toBe('App. A')
        ->and($b->citationLabel())->toBe('App. B');
});

it('labels the front-matter chapter as Front', function () {
    $chapter = new Chapter(['number' => 0, 'title' => 'Front matter: About this book']);

    expect($chapter->citationLabel())->toBe('Front');
});
