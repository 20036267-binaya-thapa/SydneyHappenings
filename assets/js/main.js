// ============================================================
// assets/js/main.js
// Small, hand-written progressive-enhancement script. The site works
// without JavaScript - this file only makes it nicer to use.
// ============================================================

// Mobile navigation toggle: shows/hides the nav list on small screens
// and keeps aria-expanded in sync for screen reader users.
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('navToggle');
    var nav = document.getElementById('siteNav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            // The mobile nav panel and the user-menu must never both be
            // open at once - opening this one closes any open user-menu.
            if (isOpen) {
                document.querySelectorAll('.user-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
            }
        });
    }

    // Confirm before any destructive action (event delete, etc). The
    // form still submits normally on confirm; the server-side check
    // and CSRF token are what actually protect the action.
    var confirmForms = document.querySelectorAll('[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm');
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Review form: live character count under the comment textarea.
    // The server still enforces 20-1000 characters on submit - this
    // just saves a round trip for the common case of typing too little
    // or too much, and updates the same text a screen reader announces
    // (aria-live="polite" on #comment-count in the markup).
    var reviewComment = document.getElementById('comment');
    var reviewCommentCount = document.getElementById('comment-count');
    if (reviewComment && reviewCommentCount) {
        var updateCommentCount = function () {
            reviewCommentCount.textContent = reviewComment.value.length + ' of 1000 characters (minimum 20).';
        };
        reviewComment.addEventListener('input', updateCommentCount);
        updateCommentCount();
    }

    // Review form: decorative star highlighting on hover and keyboard
    // focus. The radio inputs (labelled "1 star" .. "5 stars") and
    // their checked state are the real form - this only recolours the
    // aria-hidden star icons next to each label, so it changes nothing
    // about how the form works with JavaScript off.
    var starGroup = document.getElementById('ratingStars');
    if (starGroup) {
        var starWrappers = starGroup.querySelectorAll('.rating-star');

        var fillStars = function (value) {
            starWrappers.forEach(function (wrapper) {
                var input = wrapper.querySelector('input[type="radio"]');
                var icon = wrapper.querySelector('.rating-icon');
                icon.classList.toggle('is-filled', Number(input.value) <= value);
            });
        };

        var checkedStarValue = function () {
            var checked = starGroup.querySelector('input[type="radio"]:checked');
            return checked ? Number(checked.value) : 0;
        };

        // Start filled up to whatever is already checked (e.g. editing
        // an existing review); hover/focus only ever preview a value,
        // restoring the real checked value once the pointer or focus
        // moves away.
        fillStars(checkedStarValue());

        starWrappers.forEach(function (wrapper) {
            var input = wrapper.querySelector('input[type="radio"]');
            var label = wrapper.querySelector('label');

            label.addEventListener('mouseenter', function () {
                fillStars(Number(input.value));
            });
            input.addEventListener('focus', function () {
                fillStars(Number(input.value));
            });
            input.addEventListener('change', function () {
                fillStars(Number(input.value));
            });
        });

        starGroup.addEventListener('mouseleave', function () {
            fillStars(checkedStarValue());
        });
        starGroup.addEventListener('focusout', function () {
            fillStars(checkedStarValue());
        });
    }

    // Event detail page: collapse the description behind a Read
    // more/Show less button. PHP always renders the full text and the
    // toggle button starts hidden - this only adds the collapsed state
    // and reveals the button, and only when the text is actually tall
    // enough to need collapsing, so a no-JS visitor always sees the
    // complete description with no dead button.
    var eventDescription = document.getElementById('eventDescription');
    var descriptionToggle = document.getElementById('descriptionToggle');
    if (eventDescription && descriptionToggle) {
        eventDescription.classList.add('is-collapsed');

        if (eventDescription.scrollHeight > eventDescription.clientHeight) {
            descriptionToggle.hidden = false;
            descriptionToggle.textContent = 'Read more';
            descriptionToggle.setAttribute('aria-expanded', 'false');

            descriptionToggle.addEventListener('click', function () {
                var isCollapsed = eventDescription.classList.toggle('is-collapsed');
                descriptionToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                descriptionToggle.textContent = isCollapsed ? 'Read more' : 'Show less';
            });
        } else {
            eventDescription.classList.remove('is-collapsed');
        }
    }

    // Event detail page: one-click copy of the event URL. The input
    // holding the URL is always visible and selectable on its own -
    // this button is a convenience on top of that, not a replacement
    // for it, so nothing is lost when JavaScript or the Clipboard API
    // is unavailable (the button just does nothing if clicked).
    var copyButton = document.querySelector('.share-copy-btn');
    if (copyButton && navigator.clipboard) {
        var copyTargetId = copyButton.getAttribute('data-copy-target');
        var copyInput = copyTargetId ? document.getElementById(copyTargetId) : null;

        if (copyInput) {
            var defaultCopyLabel = copyButton.textContent;
            copyButton.addEventListener('click', function () {
                navigator.clipboard.writeText(copyInput.value).then(function () {
                    copyButton.textContent = 'Copied!';
                    setTimeout(function () {
                        copyButton.textContent = defaultCopyLabel;
                    }, 2000);
                });
            });
        }
    }

    // User-menu (account dropdown): a native <details>/<summary>, so
    // opening/closing already works with no JavaScript at all. This
    // only adds three conveniences on top - closing on outside click,
    // closing (and returning focus to the trigger) on Escape, and
    // making sure it never stays open alongside the mobile nav panel.
    // The menu's contents are never rebuilt or re-rendered here.
    var userMenus = document.querySelectorAll('.user-menu');
    userMenus.forEach(function (menu) {
        var summary = menu.querySelector('summary');

        menu.addEventListener('toggle', function () {
            if (menu.open) {
                // Only one menu (and never the mobile nav panel too)
                // should be open at a time.
                userMenus.forEach(function (otherMenu) {
                    if (otherMenu !== menu && otherMenu.open) {
                        otherMenu.removeAttribute('open');
                    }
                });
                if (nav) {
                    nav.classList.remove('is-open');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (menu.open && !menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });

        menu.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.open) {
                menu.removeAttribute('open');
                if (summary) {
                    summary.focus();
                }
            }
        });
    });

    // Admin/organiser tables: a fade hint on the right edge of any
    // table that actually needs horizontal scrolling. Detected here
    // rather than always shown, so narrow tables (which stack instead
    // below 768px and never overflow) never get a pointless hint. The
    // scrolling itself is plain CSS overflow-x and works without this.
    document.querySelectorAll('.data-table-wrapper').forEach(function (wrapper) {
        if (wrapper.scrollWidth > wrapper.clientWidth) {
            wrapper.classList.add('is-scrollable');
        }
    });

    // Wishlist heart buttons: intercept the plain form submit and toggle
    // the save/un-save via fetch instead of a full page reload. Every
    // .wishlist-form already works with JavaScript off (a real POST to
    // wishlist-toggle.php, which redirects back with a flash message) -
    // this only replaces that round trip when it can, and falls back to
    // the exact same real submit on any failure (network error, an
    // expired session, or any response that isn't the JSON shape it
    // expects), so nothing is ever silently swallowed.
    var wishlistToast = null;
    var wishlistToastTimer = null;

    function showWishlistToast(message) {
        if (!wishlistToast) {
            wishlistToast = document.createElement('div');
            wishlistToast.className = 'toast';
            wishlistToast.setAttribute('role', 'status');
            wishlistToast.setAttribute('aria-live', 'polite');
            document.body.appendChild(wishlistToast);
        }
        wishlistToast.textContent = message;
        wishlistToast.classList.add('is-visible');
        if (wishlistToastTimer) {
            clearTimeout(wishlistToastTimer);
        }
        wishlistToastTimer = setTimeout(function () {
            wishlistToast.classList.remove('is-visible');
        }, 2500);
    }

    document.querySelectorAll('.wishlist-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('.wishlist-btn');
            // The label span is whichever one isn't the decorative,
            // aria-hidden heart - the sr-only text on a card button or
            // the visible text on the labelled booking-panel button.
            var label = button ? button.querySelector('span:not(.wishlist-heart)') : null;

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                var contentType = response.headers.get('content-type') || '';
                if (!response.ok || contentType.indexOf('application/json') === -1) {
                    throw new Error('Unexpected wishlist-toggle response');
                }
                return response.json();
            }).then(function (data) {
                if (!button || typeof data.saved !== 'boolean') {
                    throw new Error('Unexpected wishlist-toggle response shape');
                }

                button.setAttribute('aria-pressed', data.saved ? 'true' : 'false');
                if (label) {
                    label.textContent = data.saved ? 'Saved' : 'Save event';
                }
                if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
                    button.classList.add('is-animating');
                    setTimeout(function () {
                        button.classList.remove('is-animating');
                    }, 200);
                }

                showWishlistToast(data.saved ? 'Saved to your events' : 'Removed from your saved events');
            }).catch(function () {
                // Falls all the way back to what would have happened
                // with JavaScript off: a real POST and a full page
                // reload showing the server's own flash message.
                form.submit();
            });
        });
    });

    // Ticket quantity stepper (event.php's booking panel): the +/-
    // buttons are inert in the HTML on purpose (see the markup comment
    // there) - this is what actually makes them work. The number input
    // itself already enforces min/max natively and stays directly
    // editable regardless, so nothing here is the only way to choose a
    // quantity, and register-for-event.php's own checks 7/8 are still
    // what actually stop an out-of-range booking from going through.
    var ticketQuantityInput = document.getElementById('ticketQuantity');
    var ticketQuantityDecrease = document.getElementById('ticketQuantityDecrease');
    var ticketQuantityIncrease = document.getElementById('ticketQuantityIncrease');
    var ticketQuantityRemaining = document.getElementById('ticketQuantityRemaining');

    if (ticketQuantityInput && ticketQuantityDecrease && ticketQuantityIncrease) {
        var ticketMin = Number(ticketQuantityInput.min) || 1;
        var ticketMax = Number(ticketQuantityInput.max) || ticketMin;
        var spotsRemaining = Number(ticketQuantityInput.getAttribute('data-spots-remaining')) || 0;

        // Keeps both buttons disabled exactly at the bounds, and (with
        // ticketQuantityRemaining present) shows "X places left after
        // this booking" - purely informative, the real enforcement is
        // the input's own min/max plus the server-side checks.
        var updateTicketQuantityState = function () {
            var value = Number(ticketQuantityInput.value) || ticketMin;
            ticketQuantityDecrease.disabled = value <= ticketMin;
            ticketQuantityIncrease.disabled = value >= ticketMax;

            if (ticketQuantityRemaining) {
                var afterBooking = Math.max(0, spotsRemaining - value);
                ticketQuantityRemaining.textContent = afterBooking + ' place' + (afterBooking === 1 ? '' : 's') + ' left after this booking.';
                ticketQuantityRemaining.hidden = false;
            }
        };

        ticketQuantityDecrease.addEventListener('click', function () {
            var value = Math.max(ticketMin, (Number(ticketQuantityInput.value) || ticketMin) - 1);
            ticketQuantityInput.value = value;
            updateTicketQuantityState();
        });

        ticketQuantityIncrease.addEventListener('click', function () {
            var value = Math.min(ticketMax, (Number(ticketQuantityInput.value) || ticketMin) + 1);
            ticketQuantityInput.value = value;
            updateTicketQuantityState();
        });

        // Typing a value directly (or using the native up/down arrows)
        // must keep the buttons and the live text in sync too, not just
        // clicks on the +/- buttons themselves.
        ticketQuantityInput.addEventListener('input', updateTicketQuantityState);

        updateTicketQuantityState();
    }

    // Print ticket button (booking-confirmation.php): a plain link that
    // works with no JavaScript at all (it just reloads the same,
    // already-printable confirmation page) - this only replaces that
    // with an instant print dialog when it can.
    var printTicketBtn = document.querySelector('.print-ticket-btn');
    if (printTicketBtn) {
        printTicketBtn.addEventListener('click', function (event) {
            event.preventDefault();
            window.print();
        });
    }

    // Sticky header shadow/blur once the page has scrolled past 20px.
    var siteHeader = document.getElementById('siteHeader');
    if (siteHeader) {
        var updateHeaderScrolled = function () {
            siteHeader.classList.toggle('is-scrolled', window.scrollY > 20);
        };
        window.addEventListener('scroll', updateHeaderScrolled, { passive: true });
        updateHeaderScrolled();
    }
});
