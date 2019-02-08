<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
/**
 * Modified a11y-dark theme, itself based on the Tomorrow Night Eighties theme
 * by ericwbailey.
 */
@font-face {
    font-family: "Roboto Mono Regular";
    src: url("<?php echo __DIR__ . '/../assets/fonts/RobotoMono-Regular.ttf'; ?>") format("truetype");
    font-weight: normal;
    font-style: normal;
}

@font-face {
    font-family: "Roboto Mono Regular";
    src: url("<?php echo __DIR__ . '/../assets/fonts/RobotoMono-Italic.ttf'; ?>") format("truetype");
    font-weight: normal;
    font-style: italic;
}

@font-face {
    font-family: "Roboto Mono Bold";
    src: url("<?php echo __DIR__ . '/../assets/fonts/RobotoMono-Bold.ttf'; ?>") format("truetype");
    font-weight: normal;
    font-style: normal;
}

@font-face {
    font-family: "Roboto Mono Bold";
    src: url("<?php echo __DIR__ . '/../assets/fonts/RobotoMono-BoldItalic.ttf'; ?>") format("truetype");
    font-weight: normal;
    font-style: italic;
}

/* Comment */
.hljs-comment,
.hljs-quote {
    color: #d4d0ab;
}

/* Red */
.hljs-variable,
.hljs-template-variable,
.hljs-tag,
.hljs-name,
.hljs-selector-id,
.hljs-selector-class,
.hljs-regexp,
.hljs-deletion {
    color: #ffa07a;
}

/* Orange */
.hljs-number,
.hljs-built_in,
.hljs-builtin-name,
.hljs-literal,
.hljs-type,
.hljs-params,
.hljs-meta,
.hljs-link {
    color: #f5ab35;
}

/* Yellow */
.hljs-attribute {
    color: #ffd700;
}

/* Green */
.hljs-string,
.hljs-symbol,
.hljs-bullet,
.hljs-addition {
    color: #abe338;
}

/* Blue */
.hljs-title,
.hljs-section {
    color: #00e0e0;
}

/* Purple */
.hljs-keyword,
.hljs-selector-tag {
    color: #dcc6e0;
}

* {
    margin: 0;
    padding: 0;
}

body {
    font-size: 25px;
}

.hljs {
    color: #f8f8f2;
    font-family: 'Roboto Mono Regular';
    overflow: hidden;
    background: transparent;
    height: 535px; /* A fairly random number that seems to cut off text in about the right place. */
}
/*
.hljs-comment,
.hljs-quote,
.hljs-emphasis {
    font-style: italic;
}

.hljs-strong,
.hljs-keyword,
.hljs-selector-tag {
    font-family: 'Roboto Mono Bold';
}
*/
</style>
</head>
<body>
<?php echo $str; ?>
</body>
</html>
