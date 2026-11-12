<style>
    .marks-submitbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 14px 20px;
        border-top: 1px dashed var(--line-strong);
        background: linear-gradient(180deg, rgba(248, 250, 252, .7), rgba(255, 255, 255, .95));
    }

    .marks-submitbar__hint {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        max-width: 560px;
        color: var(--ink-soft);
        font-size: .78rem;
        line-height: 1.5;
    }

    .marks-submitbar__hint p { margin: 0; }

    .marks-submitbar__icon {
        width: 34px;
        height: 34px;
        flex: none;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: var(--brand-50);
        color: var(--brand-700);
        font-size: .95rem;
    }
</style>
