<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/**
 * What a member has earned in Syllabus: their certificates, and the skills
 * each came with. Part of Syllabus's public contract (see the README): other
 * extensions may resolve this class and call forMember(), and the shape it
 * returns is stable. Also served at GET /api/syllabus/users/{id}/achievements.
 *
 * Privacy follows the profile's Courses tab: the member themselves and course
 * managers always see it; anyone else only if the member shows their courses
 * and may view courses at all. Certificates are listed whether or not their
 * course is still visible (they stay valid); the course is linked only when
 * the viewer may see it.
 */
class Achievements
{
    public function __construct(private UrlGenerator $url, private SettingsRepositoryInterface $settings)
    {
    }

    public function canSee(User $actor, User $member): bool
    {
        if (! $actor->isGuest() && $actor->id === $member->id) {
            return true;
        }

        return Access::isManager($actor)
            || ($actor->hasPermission('syllabus.view') && (bool) $member->getPreference('syllabusShowCourses', true));
    }

    /**
     * The member's certificates (newest first) and earned skills, or null if
     * the actor may not see them.
     *
     * @return array{certificates: list<array<string, mixed>>, skills: list<array<string, mixed>>}|null
     */
    public function forMember(User $member, User $actor): ?array
    {
        if (! $this->canSee($actor, $member)) {
            return null;
        }

        $certificates = Certificate::query()->where('user_id', $member->id)->orderByDesc('issued_at')->orderByDesc('id')->get();
        $courseIds = $certificates->pluck('course_id')->filter()->unique()->values()->all();
        $courses = $courseIds ? Course::query()->whereIn('id', $courseIds)->get()->keyBy('id') : collect();
        $visible = $courseIds ? Course::query()->visibleTo($actor)->whereIn('id', $courseIds)->pluck('id')->all() : [];
        $issuer = (string) $this->settings->get('forum_title');

        $out = ['certificates' => [], 'skills' => []];
        foreach ($certificates as $certificate) {
            $course = $courses->get($certificate->course_id);
            // Older certificates (before 1.4) kept no skills: their course's current ones stand in.
            $skills = $certificate->skills ?? ($course->skills ?? []);
            $out['certificates'][] = [
                'code' => $certificate->code,
                'courseTitle' => $certificate->course_title,
                'course' => $course && in_array($course->id, $visible, true) ? ['id' => $course->id, 'slug' => $course->slug] : null,
                'issuer' => $issuer,
                'issuedAt' => $certificate->issued_at->toIso8601String(),
                'verifyUrl' => $this->url->to('forum')->route('syllabus.certificate', ['code' => $certificate->code]),
                'skills' => array_values($skills),
            ];
            foreach ($skills as $skill) {
                $out['skills'][] = ['name' => $skill, 'courseTitle' => $certificate->course_title, 'certificateCode' => $certificate->code];
            }
        }

        return $out;
    }
}
