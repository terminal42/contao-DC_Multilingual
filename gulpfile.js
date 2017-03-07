'use strict';

const gulp = require('gulp');
const rename = require('gulp-rename');
const gutil = require('gulp-util');
const cleanCSS = require('gulp-clean-css');

const production = true;

// Configuration
const styles = [
    'src/Resources/public/*.css',
    '!src/Resources/public/*.min.css'
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
            'src/Resources/public/*.css'
        ],
        ['styles']
    );
});

// Build by default
gulp.task('default', ['styles']);
