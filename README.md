# Single-point rubric (gradingform_singlepoint)

An advanced grading method for Moodle: a rubric with one column. Each criterion has a single description of the standard you expect, and when grading you mark it **Not yet**, **Meets** or **Beyond**, with a comment where it helps.

A classic rubric makes you write four or five levels per criterion, and students read the top column and skip the rest. A single-point rubric describes only the target, so the feedback goes where it is needed: what is missing, or what went further. Moodle's own rubric needs at least two levels per criterion with points, and its marking guide asks for a score per criterion; neither has this shape.

## Use it

1. In an assignment or forum, set **Grading method** to **Single-point rubric**.
2. Open **Define single-point rubric** from Advanced grading. Add criteria: a name, what meeting the standard looks like, and its points. The editor works without JavaScript.
3. Grade: for each criterion choose Not yet, Meets or Beyond, and add a comment.
4. Students see the rubric with your marks and comments in their feedback, and before grading if you leave **Show the rubric to students before grading** on.

## How the grade is worked out

- Meets and Beyond earn all of the criterion's points. Beyond is feedback, not extra credit, so a grade never goes over the maximum.
- Not yet earns 0% of the points by default; you can set it to 25%, 50% or 75%.
- The points are then scaled to the activity's grade, the same way core's marking guide does it.

Example: two criteria worth 10 points each, one Meets and one Not yet, in an assignment out of 100: 50.

## Where it works

- Assignments (the grading page, and the student's feedback).
- Whole forum grading (the forum grader's side panel).
- Rubric templates, backup and restore, and the privacy API (marks and comments are exported and deleted with the rest of the grading data).

Changing points or removing criteria after grading asks whether to mark the graded students for regrading.

Moodle 4.5 to 5.3. Built by DevLMS: https://devlms.com
