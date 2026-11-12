<style>
    .attendance-savebar {
        position: sticky;
        bottom: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 16px 20px;
        border-top: 1px solid var(--line);
        background: rgba(255, 255, 255, .94);
        backdrop-filter: blur(10px);
        box-shadow: 0 -18px 30px -30px rgba(15, 23, 42, .55);
    }

    .attendance-savebar__hint {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        max-width: 540px;
        color: var(--ink-soft);
        font-size: .78rem;
        line-height: 1.5;
    }

    .attendance-submitbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 14px 20px;
        border-top: 1px dashed var(--line-strong);
        background: linear-gradient(180deg, rgba(248, 250, 252, .7), rgba(255, 255, 255, .95));
    }

    .attendance-submitbar__hint {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        max-width: 560px;
        color: var(--ink-soft);
        font-size: .78rem;
        line-height: 1.5;
    }

    .attendance-submitbar__hint p { margin: 0; }

    .attendance-savebar__hint p { margin: 0; }

    .attendance-savebar__icon {
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

    .attendance-savebar__actions {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .attendance-savebar__reason label {
        display: block;
        margin-bottom: 5px;
        font-size: .66rem;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--ink-soft);
    }

    .attendance-savebar__input {
        min-width: 250px;
        padding: 9px 13px;
        font-size: .82rem;
        color: var(--ink);
        background: #fff;
        border: 1px solid var(--line-strong);
        border-radius: 11px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .attendance-savebar__input::placeholder { color: #94a3b8; }

    .attendance-savebar__input:focus {
        outline: none;
        border-color: var(--brand-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .16);
    }

    .attendance-savebar__input.is-invalid {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, .12);
    }

    .attendance-savebar__submit {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: 10px 20px;
        font-weight: 600;
        border-radius: 11px;
        box-shadow: 0 14px 24px -16px rgba(37, 99, 235, .8);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .attendance-savebar__submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 28px -16px rgba(37, 99, 235, .85);
    }

    @media (max-width: 575.98px) {
        .attendance-savebar { padding: 14px; }
        .attendance-savebar__actions { width: 100%; }
        .attendance-savebar__reason { width: 100%; }
        .attendance-savebar__input { width: 100%; min-width: 0; }
        .attendance-savebar__submit { width: 100%; justify-content: center; }
    }

    @media print {
        .attendance-savebar { display: none; }
    }
</style>
