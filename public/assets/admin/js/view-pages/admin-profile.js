/* --------------------------------------------------------------------------
   Admin profile settings (admin/settings)
   Avatar preview, section tabs, password strength/rules and match state.
   -------------------------------------------------------------------------- */
"use strict";

(function () {
    const page = document.getElementById("adminProfilePage");

    if (!page) {
        return;
    }

    /* ---------- avatar preview ---------- */
    const avatarInput = page.querySelector("#adminAvatarInput");
    const avatarImage = page.querySelector("#adminAvatarPreview");

    if (avatarInput && avatarImage) {
        avatarInput.addEventListener("change", function () {
            const file = this.files && this.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                avatarImage.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------- section tabs ----------
       The two sections stay stacked on the page; the tabs scroll to them and
       track whichever one the reader is actually looking at. */
    const tabs = Array.from(page.querySelectorAll("[data-ap-tab]"));
    // A tab can own more than one card (the identity header and the basic
    // information form are both "general"), so cards are tagged by section
    // rather than looked up by the tab's own id.
    const sections = Array.from(page.querySelectorAll("[data-ap-section]"));

    const activateTab = (id) => {
        tabs.forEach((tab) => tab.classList.toggle("is-active", tab.dataset.apTab === id));
    };

    // On a wide screen both sections are on screen at once, so the observer
    // would immediately overrule a click — and the smooth scroll it starts
    // makes it fire again mid-flight. A click therefore pins the tab until the
    // reader scrolls the page themselves.
    let pinnedTab = null;

    tabs.forEach((tab) => {
        tab.addEventListener("click", function () {
            const target = document.getElementById(this.dataset.apTab);

            if (!target) {
                return;
            }

            pinnedTab = this.dataset.apTab;
            activateTab(pinnedTab);

            // Fixed navbar (60px) + the sticky tab bar, so the card heading
            // lands in view rather than behind them.
            const wanted = target.getBoundingClientRect().top + window.pageYOffset - 132;
            const furthest = document.documentElement.scrollHeight - window.innerHeight;

            window.scrollTo({ top: wanted, behavior: "smooth" });

            // When the page really can scroll that far the observer will settle
            // on the right section by itself, so the pin is only a bridge over
            // the animation. When it cannot — a short page where both sections
            // sit on screen together — the pin has to hold.
            if (wanted <= furthest) {
                window.setTimeout(() => {
                    pinnedTab = null;
                }, 900);
            }
        });
    });

    ["wheel", "touchmove", "keydown"].forEach((event) => {
        window.addEventListener(event, () => {
            pinnedTab = null;
        }, { passive: true });
    });

    if (sections.length && "IntersectionObserver" in window) {
        // The topmost visible section wins rather than the largest slice of
        // viewport, which would otherwise favour whichever card is taller.
        const observer = new IntersectionObserver(
            () => {
                if (pinnedTab) {
                    return;
                }

                const visible = sections
                    .filter((section) => {
                        const box = section.getBoundingClientRect();
                        return box.bottom > 150 && box.top < window.innerHeight * 0.6;
                    })
                    .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top)[0];

                if (visible) {
                    activateTab(visible.dataset.apSection);
                }
            },
            { rootMargin: "-140px 0px -40% 0px", threshold: [0, 0.15, 0.5, 0.9] }
        );

        sections.forEach((section) => observer.observe(section));
    }

    /* ---------- basic information reset ---------- */
    const infoForm = document.getElementById("admin-settings-form");
    const resetButton = page.querySelector("[data-ap-reset]");

    if (infoForm && resetButton) {
        resetButton.addEventListener("click", () => {
            infoForm.reset();

            if (avatarImage && avatarImage.dataset.originalSrc) {
                avatarImage.src = avatarImage.dataset.originalSrc;
            }
        });
    }

    /* ---------- password strength + rules ----------
       Mirrors the server rule set (Password::min(8)->mixedCase()->letters()
       ->numbers()->symbols()), so the checklist never promises a password the
       backend would reject. */
    const passwordInput = page.querySelector("#adminNewPassword");
    const confirmInput = page.querySelector("#adminConfirmPassword");

    if (!passwordInput || !confirmInput) {
        return;
    }

    const strength = page.querySelector("#adminPasswordStrength");
    const strengthLabel = page.querySelector("#adminPasswordStrengthLabel");
    const matchNote = page.querySelector("#adminPasswordMatch");
    const submitButton = page.querySelector("[data-ap-password-submit]");
    const ruleItems = Array.from(page.querySelectorAll("[data-ap-rule]"));
    const labels = strength ? JSON.parse(strength.dataset.labels || "[]") : [];

    const rules = {
        length: (value) => value.length >= 8,
        lowercase: (value) => /[a-z]/.test(value),
        uppercase: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };

    const evaluate = () => {
        const value = passwordInput.value;
        const confirmValue = confirmInput.value;
        let met = 0;

        ruleItems.forEach((item) => {
            const check = rules[item.dataset.apRule];
            const passed = Boolean(check && value && check(value));

            item.classList.toggle("is-met", passed);
            item.querySelector("i").className = passed
                ? "tio-checkmark-circle"
                : "tio-circle-outlined";

            if (passed) {
                met += 1;
            }
        });

        if (strength) {
            const level = value ? met : 0;
            strength.dataset.level = String(level);

            if (strengthLabel) {
                strengthLabel.textContent = labels[level] || "";
            }
        }

        const allMet = met === ruleItems.length;
        const matches = confirmValue !== "" && value === confirmValue;

        if (matchNote) {
            matchNote.classList.toggle("is-visible", confirmValue !== "");
            matchNote.classList.toggle("ap-match--ok", matches);
            matchNote.classList.toggle("ap-match--error", !matches);
            matchNote.querySelector("i").className = matches
                ? "tio-checkmark-circle"
                : "tio-clear-circle";
            matchNote.querySelector("span").textContent = matches
                ? matchNote.dataset.okText
                : matchNote.dataset.errorText;
        }

        if (submitButton) {
            const ready = allMet && matches;
            submitButton.disabled = !ready;
            submitButton.classList.toggle("disabled", !ready);
        }
    };

    passwordInput.addEventListener("input", evaluate);
    confirmInput.addEventListener("input", evaluate);
    evaluate();

    /* ---------- show / hide password ---------- */
    page.querySelectorAll("[data-ap-toggle]").forEach((button) => {
        button.addEventListener("click", function () {
            const field = document.getElementById(this.dataset.apToggle);

            if (!field) {
                return;
            }

            const revealed = field.type === "text";
            field.type = revealed ? "password" : "text";
            this.querySelector("i").className = revealed
                ? "tio-visible-outlined"
                : "tio-hidden-outlined";
            this.setAttribute("aria-label", revealed ? this.dataset.showText : this.dataset.hideText);
        });
    });
})();
