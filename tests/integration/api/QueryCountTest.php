<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The catalogue and a course page cost the same number of queries for one item or many. */
class QueryCountTest extends SyllabusTestCase
{
    protected function extensions(): array
    {
        return ['flarum-mentions', 'ernestdefoe-syllabus'];
    }

    private function queries(string $path, array $query = [], int $actor = 2): int
    {
        $queries = 0;
        $this->database()->listen(function () use (&$queries) {
            $queries++;
        });
        $request = $this->request('GET', $path, ['authenticatedAs' => $actor])->withQueryParams($query);
        $this->send($request); // warm
        $queries = 0;
        $response = $this->send($request);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $queries;
    }

    #[Test]
    public function the_catalogue_and_my_courses_do_not_grow_with_the_course_count(): void
    {
        $this->json('POST', '/api/syllabus/categories', 1, ['data' => ['name' => 'Cooking']]);
        $first = $this->course(['description' => 'Thanks @"normal"#2', 'categoryId' => 1], 4);
        $this->lesson($first['id'], [], 4);
        $this->enrol($first['id']);

        $one = $this->queries('/api/syllabus/courses');
        $mineOne = $this->queries('/api/syllabus/courses', ['filter' => ['mine' => '1']]);
        $teachOne = $this->queries('/api/syllabus/teaching', [], 4);

        for ($i = 0; $i < 6; $i++) {
            $course = $this->course(['title' => "Course $i", 'categoryId' => 1, 'description' => "Hi @\"admin\"#1 $i"], $i % 2 ? 4 : 5);
            $this->lesson($course['id'], [], $i % 2 ? 4 : 5);
            $this->enrol($course['id']);
        }

        $this->assertSame($one, $this->queries('/api/syllabus/courses'));
        $this->assertSame($mineOne, $this->queries('/api/syllabus/courses', ['filter' => ['mine' => '1']]));
        $this->assertSame($teachOne, $this->queries('/api/syllabus/teaching', [], 4));
    }

    #[Test]
    public function a_course_page_does_not_grow_with_the_lesson_count(): void
    {
        $course = $this->course();
        $first = $this->lesson($course['id']);
        $this->enrol($course['id']);
        $this->complete($first['id']);

        $one = $this->queries('/api/syllabus/courses/'.$course['id']);
        $lessonOne = $this->queries('/api/syllabus/lessons/'.$first['id']);

        for ($i = 0; $i < 10; $i++) {
            $lesson = $this->lesson($course['id'], ['title' => "Lesson $i"]);
            $this->complete($lesson['id']);
        }
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'].'/outline', 1, ['data' => ['items' => [['kind' => 'section', 'title' => 'Part one']]]]);

        $this->assertSame($one, $this->queries('/api/syllabus/courses/'.$course['id']));
        $this->assertSame($lessonOne, $this->queries('/api/syllabus/lessons/'.$first['id']));
    }
}
