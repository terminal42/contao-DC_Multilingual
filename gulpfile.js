'use strict';

const gulp = require('gulp');
const rename = require('gulp-rename');
const gutil = require('gulp-util');
const cleanCSS = require('gulp-clean-css');

const production = true;

// Configuration
const styles = [
    'public/*.css',
    '!public/*.min.css'
];

// Build styles
gulp.task('styles', function () {
    return gulp.src(styles, {base: './'})
    .pipe(production ? cleanCSS({'restructuring': false, 'processImport': false}) : gutil.noop())
    .pipe(rename(function (path) {
        path.extname = '.min' + path.extname;
    }))
    .pipe(gulp.dest('./'));
});

// Watch task
gulp.task('watch', function () {
    gulp.watch(
        [
            'public/*.css'
        ],
        ['styles']
    );
});

// Build by default
gulp.task('default', ['styles']);
