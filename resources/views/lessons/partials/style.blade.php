{{--
    Lesson screens: the editor chrome and the typography of a written lesson.
    Kept with the lesson views so the styles arrive with the fragment the
    teacher dashboard injects, and nothing outside this module changes.
--}}
<style>
    .lsn-shell {
        display: grid;
        gap: 1rem;
    }

    .lsn-stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: .75rem;
    }

    .lsn-stat {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-card);
        padding: .85rem 1rem;
    }

    .lsn-stat-value {
        display: block;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--navy-900);
        line-height: 1.2;
    }

    .lsn-stat-label {
        display: flex;
        align-items: center;
        gap: .35rem;
        font-size: .74rem;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--muted);
        margin-top: .25rem;
    }

    .lsn-stat.is-accent .lsn-stat-value { color: var(--brand-700); }
    .lsn-stat.is-draft .lsn-stat-value { color: var(--warning); }

    /* --- editor ----------------------------------------------------------- */
    .lsn-editor-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-card);
        overflow: hidden;
    }

    .lsn-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem;
        align-items: center;
        padding: .5rem .65rem;
        background: var(--brand-50);
        border-bottom: 1px solid var(--line);
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .lsn-toolbar-group {
        display: inline-flex;
        gap: .2rem;
        align-items: center;
        padding-right: .5rem;
        margin-right: .25rem;
        border-right: 1px solid var(--line-strong);
    }

    .lsn-toolbar-group:last-child { border-right: 0; }

    .lsn-tool {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 .4rem;
        border: 1px solid transparent;
        border-radius: var(--radius-sm);
        background: transparent;
        color: var(--navy-800);
        font-size: .82rem;
        cursor: pointer;
    }

    .lsn-tool:hover {
        background: #fff;
        border-color: var(--line-strong);
    }

    .lsn-tool:focus-visible {
        outline: 2px solid var(--brand-500);
        outline-offset: 1px;
    }

    .lsn-tool.lsn-tool-block {
        width: auto;
        border: 1px solid var(--line-strong);
        border-radius: var(--radius-sm);
        background: #fff;
        color: var(--ink);
        font-size: .78rem;
        padding: 0 .35rem;
        cursor: pointer;
    }

    .lsn-editor {
        min-height: 320px;
        padding: 1rem 1.15rem;
        outline: none;
        font-size: .95rem;
        line-height: 1.65;
        color: var(--ink);
        overflow-wrap: break-word;
    }

    .lsn-editor:empty::before,
    .lsn-editor.is-blank::before {
        content: attr(data-placeholder);
        color: var(--muted);
        pointer-events: none;
    }

    .lsn-editor-foot {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem 1rem;
        justify-content: space-between;
        align-items: center;
        padding: .55rem 1rem;
        border-top: 1px solid var(--line);
        background: #fbfdff;
        font-size: .76rem;
        color: var(--muted);
    }

    /* --- rendered lesson body -------------------------------------------- */
    .lesson-body {
        color: var(--ink);
        font-size: .95rem;
        line-height: 1.7;
        overflow-wrap: break-word;
    }

    .lesson-body > :first-child { margin-top: 0; }

    .lesson-body h2,
    .lesson-body h3,
    .lesson-body h4 {
        color: var(--navy-900);
        font-weight: 700;
        margin: 1.4rem 0 .55rem;
        line-height: 1.3;
    }

    .lesson-body h2 { font-size: 1.24rem; }
    .lesson-body h3 { font-size: 1.1rem; }
    .lesson-body h4 { font-size: 1rem; }

    .lesson-body p { margin: 0 0 .85rem; }

    .lesson-body ul,
    .lesson-body ol { margin: 0 0 .9rem; padding-left: 1.4rem; }

    .lesson-body li { margin-bottom: .3rem; }

    .lesson-body a { color: var(--brand-700); text-decoration: underline; }

    .lesson-body img {
        max-width: 100%;
        height: auto;
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        margin: .4rem 0 .9rem;
    }

    .lesson-body blockquote {
        margin: 0 0 .9rem;
        padding: .6rem .9rem;
        border-left: 3px solid var(--brand-500);
        background: var(--brand-50);
        border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        color: var(--ink-soft);
    }

    .lesson-body table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 1rem;
        font-size: .88rem;
    }

    .lesson-body th,
    .lesson-body td {
        border: 1px solid var(--line-strong);
        padding: .45rem .6rem;
        text-align: start;
        vertical-align: top;
    }

    .lesson-body th { background: var(--brand-50); color: var(--navy-900); }

    .lesson-body code {
        background: #f1f5fb;
        border-radius: 6px;
        padding: .1rem .3rem;
        font-size: .85em;
    }

    .lesson-body pre {
        background: var(--navy-950);
        color: #e8f0fb;
        border-radius: var(--radius-sm);
        padding: .8rem 1rem;
        overflow-x: auto;
        font-size: .82rem;
    }

    .lesson-body hr { border: 0; border-top: 1px solid var(--line); margin: 1.1rem 0; }

    /* --- live preview ----------------------------------------------------- */
    .lsn-preview {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-card);
        padding: 1rem 1.15rem;
        max-height: 70vh;
        overflow: auto;
    }

    .lsn-preview-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        margin-bottom: .6rem;
    }

    .lsn-material-row {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem 0;
        border-bottom: 1px dashed var(--line);
    }

    .lsn-material-row:last-child { border-bottom: 0; }

    .lsn-video {
        position: relative;
        padding-top: 56.25%;
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--navy-950);
        border: 1px solid var(--line);
    }

    .lsn-video iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }

    .lsn-banner {
        display: none;
        align-items: center;
        gap: .5rem;
        padding: .6rem .9rem;
        border-radius: var(--radius-sm);
        font-size: .85rem;
        margin-bottom: .85rem;
    }

    .lsn-banner.is-show { display: flex; }
    .lsn-banner.is-ok { background: var(--success-soft); color: #0b7a55; border: 1px solid #b7e6d3; }
    .lsn-banner.is-bad { background: var(--danger-soft); color: #a32032; border: 1px solid #f4c5cc; }

    .lsn-error { font-size: .76rem; color: var(--danger); min-height: 0; }
    .lsn-error.is-show { display: block; }

    tr[data-lsn-row].is-leaving { opacity: .35; }

    /* --- a lesson as the class reads it ------------------------------------ */
    .lsn-read {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        padding: 1.25rem 1.35rem;
    }

    .lsn-read-head { border-bottom: 1px solid var(--line); padding-bottom: .85rem; margin-bottom: 1rem; }

    .lsn-read-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: var(--navy-900);
        margin: .3rem 0 .5rem;
        line-height: 1.25;
    }

    .lsn-read-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem 1.1rem;
        margin: 0;
        font-size: .8rem;
        color: var(--muted);
    }

    .lsn-read-meta i { color: var(--brand-600); margin-right: .25rem; }

    .lsn-read-lede {
        font-size: .95rem;
        color: var(--ink-soft);
        background: var(--brand-50);
        border-left: 3px solid var(--brand-500);
        border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        padding: .65rem .9rem;
        margin-bottom: 1rem;
    }

    .lsn-objectives {
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        padding: .85rem 1rem;
        margin-bottom: 1.15rem;
        background: #fbfdff;
    }

    .lsn-objectives h2 {
        font-size: .9rem;
        font-weight: 700;
        color: var(--navy-900);
        margin: 0 0 .5rem;
    }

    .lsn-objectives ul { margin: 0; padding-left: 1.2rem; font-size: .89rem; }

    .lsn-objectives li { margin-bottom: .25rem; }

    .lsn-read h2.lsn-section-title,
    .lsn-read .h6 {
        color: var(--navy-900);
        font-weight: 700;
        letter-spacing: .01em;
        margin-bottom: .5rem;
    }

    .lsn-read .h6 i { color: var(--brand-600); margin-right: .3rem; }

    .lsn-material-list {
        list-style: none;
        margin: 0;
        padding: 0;
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        padding: 0 .85rem;
    }

    .lsn-links {
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: .85rem;
        word-break: break-all;
    }

    .lsn-links li { padding: .3rem 0; border-bottom: 1px dashed var(--line); }
    .lsn-links li:last-child { border-bottom: 0; }
    .lsn-links a { color: var(--brand-700); }

    /* --- the class's own lesson shelf -------------------------------------- */
    .lsn-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(268px, 1fr));
        gap: .9rem;
    }

    .lsn-card {
        display: flex;
        flex-direction: column;
        gap: .45rem;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        padding: 1rem 1.05rem;
    }

    .lsn-card:hover {
        border-color: var(--brand-200);
        box-shadow: var(--shadow-card);
        transform: translateY(-2px);
    }

    .lsn-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .lsn-card-title {
        font-size: 1.02rem;
        font-weight: 700;
        color: var(--navy-900);
        margin: 0;
        line-height: 1.35;
    }

    .lsn-card-chapter {
        font-size: .78rem;
        color: var(--brand-700);
        margin: 0;
    }

    .lsn-card-note {
        font-size: .84rem;
        color: var(--ink-soft);
        margin: 0;
        flex-grow: 1;
    }

    .lsn-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        border-top: 1px solid var(--line);
        padding-top: .6rem;
        margin-top: .2rem;
    }

    /* --- staff detail ------------------------------------------------------ */
    .lsn-detail-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: .75rem 1rem;
        font-size: .83rem;
    }

    .lsn-detail-meta dt {
        color: var(--muted);
        font-size: .72rem;
        letter-spacing: .03em;
        text-transform: uppercase;
        font-weight: 600;
    }

    .lsn-detail-meta dd { margin: .1rem 0 0; color: var(--ink); font-weight: 600; }

    .lsn-history { list-style: none; margin: 0; padding: 0; font-size: .82rem; }

    .lsn-history li {
        padding: .5rem 0;
        border-bottom: 1px dashed var(--line);
        display: flex;
        gap: .6rem;
        align-items: baseline;
    }

    .lsn-history li:last-child { border-bottom: 0; }

    .lsn-preview-flag {        background: var(--warning-soft);
        border: 1px solid #f3d9a8;
        color: #8a5a06;
        border-radius: var(--radius-sm);
        padding: .55rem .85rem;
        font-size: .82rem;
        margin-bottom: .9rem;
        display: flex;
        gap: .5rem;
        align-items: center;
    }

    @media (max-width: 991.98px) {
        .lsn-editor { min-height: 260px; padding: .85rem; }
        .lsn-toolbar { position: static; }
    }
</style>
