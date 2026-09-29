{{-- Rating Modal Component for Video Call --}}
<div id="ratingModal" class="rating-modal-overlay is-hidden" aria-hidden="true">
    <div class="rating-modal-content" role="dialog" aria-modal="true" aria-labelledby="ratingModalTitle">
        <div class="rating-modal-header">
            <div class="rating-modal-badge">
                <i class="ti ti-heart-handshake"></i>
                <span>Consultation Complete</span>
            </div>
            <button type="button" class="rating-modal-close" id="btnSkipRating" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <div class="rating-modal-body">
            <div class="rating-hero-card">
                <div class="rating-hero-avatar">
                    <img id="ratingDoctorAvatar" class="rating-doctor-avatar is-hidden" src="" alt="Doctor avatar">
                    <div id="ratingDoctorAvatarFallback" class="rating-doctor-avatar-fallback">DR</div>
                </div>

                <div class="rating-hero-copy">
                    <p class="rating-kicker">Post-consultation feedback</p>
                    <h2 id="ratingModalTitle">How was your consultation?</h2>
                    <p class="rating-hero-text">Your feedback helps us improve care quality and the telemedicine experience.</p>
                    <div class="rating-doctor-chip">
                        <i class="ti ti-stethoscope"></i>
                        <span id="ratingDoctorName">Dr. John Doe</span>
                    </div>
                </div>
            </div>

            <div class="rating-divider"></div>

            <div class="rating-stars-section">
                <p class="rating-stars-label">Rate your session</p>
                <div class="rating-stars" id="ratingStars">
                    <button class="rating-star" data-value="1" title="Poor" type="button" aria-label="Rate 1 star">
                        <i class="ti ti-star-filled"></i>
                    </button>
                    <button class="rating-star" data-value="2" title="Fair" type="button" aria-label="Rate 2 stars">
                        <i class="ti ti-star-filled"></i>
                    </button>
                    <button class="rating-star" data-value="3" title="Good" type="button" aria-label="Rate 3 stars">
                        <i class="ti ti-star-filled"></i>
                    </button>
                    <button class="rating-star" data-value="4" title="Very Good" type="button" aria-label="Rate 4 stars">
                        <i class="ti ti-star-filled"></i>
                    </button>
                    <button class="rating-star" data-value="5" title="Excellent" type="button" aria-label="Rate 5 stars">
                        <i class="ti ti-star-filled"></i>
                    </button>
                </div>
                <div class="rating-stars-feedback" id="ratingFeedback">Select a rating to continue</div>
            </div>

            <div class="rating-review-section">
                <label for="ratingReview" class="rating-review-label">
                    <i class="ti ti-message-2-heart"></i>
                    <span>Share your experience</span>
                </label>

                <div class="rating-review-field">
                    <textarea
                        id="ratingReview"
                        class="rating-review-textarea"
                        placeholder="Share your experience with the doctor..."
                        rows="4"
                        maxlength="2000"
                    ></textarea>
                    <div class="rating-review-counter">
                        <span id="ratingCharCount">0</span>/2000
                    </div>
                </div>
            </div>
        </div>

        <div class="rating-modal-footer">
            <button type="button" class="rating-btn-skip" id="btnSkipSubmit">
                <i class="ti ti-arrow-right-circle"></i>
                <span>Skip for now</span>
            </button>

            <button type="button" class="rating-btn-submit" id="btnSubmitRating" disabled>
                <span class="rating-btn-icon">
                    <i class="ti ti-check"></i>
                </span>
                <span class="rating-btn-label">Submit Review</span>
                <span class="rating-btn-spinner" aria-hidden="true"></span>
            </button>
        </div>

        <div class="rating-loading-overlay" id="ratingLoadingOverlay" style="display: none;">
            <div class="rating-spinner-card">
                <div class="spinner-ring"></div>
                <p>Saving your review...</p>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --rating-primary: #1a6fc4;
    --rating-primary-dk: #145aa0;
    --rating-accent: #17b890;
    --rating-accent-soft: rgba(23, 184, 144, 0.14);
    --rating-bg-dark: #0f172a;
    --rating-bg-card: rgba(15, 23, 42, 0.92);
    --rating-bg-panel: rgba(255, 255, 255, 0.08);
    --rating-bg-input: rgba(255, 255, 255, 0.06);
    --rating-text-primary: #f8fafc;
    --rating-text-secondary: #cbd5e1;
    --rating-border: rgba(148, 163, 184, 0.22);
    --rating-border-strong: rgba(255, 255, 255, 0.14);
    --rating-success: #198754;
    --rating-warning: #fbbf24;
    --rating-shadow: 0 30px 80px rgba(15, 23, 42, 0.36);
}

body.light-theme,
body:not(.dark-theme) {
    --rating-bg-dark: #f8fbff;
    --rating-bg-card: rgba(255, 255, 255, 0.96);
    --rating-bg-panel: rgba(241, 245, 249, 0.95);
    --rating-bg-input: rgba(248, 250, 252, 0.98);
    --rating-text-primary: #0f172a;
    --rating-text-secondary: #64748b;
    --rating-border: rgba(148, 163, 184, 0.22);
    --rating-border-strong: rgba(203, 213, 225, 0.72);
    --rating-shadow: 0 28px 80px rgba(15, 23, 42, 0.16);
}

.rating-modal-overlay {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background:
        radial-gradient(circle at top, rgba(26, 111, 196, 0.18), transparent 34%),
        linear-gradient(180deg, rgba(15, 23, 42, 0.28), rgba(15, 23, 42, 0.72));
    backdrop-filter: blur(10px);
    z-index: 9999;
}

.is-hidden {
    display: none !important;
}

.rating-modal-overlay.is-hidden {
    display: none;
}

.rating-modal-content {
    position: relative;
    width: min(100%, 520px);
    max-height: min(92vh, 760px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 28px;
    border: 1px solid var(--rating-border-strong);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.06), transparent 18%),
        var(--rating-bg-card);
    box-shadow: var(--rating-shadow);
    animation: ratingModalEnter 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes ratingModalEnter {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.rating-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.25rem 0;
}

.rating-modal-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.9rem;
    border-radius: 999px;
    background: var(--rating-accent-soft);
    color: var(--rating-accent);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.rating-modal-close {
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 14px;
    background: transparent;
    color: var(--rating-text-secondary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    transition: all 0.2s ease;
}

.rating-modal-close:hover:not(:disabled) {
    color: var(--rating-text-primary);
    background: rgba(148, 163, 184, 0.12);
    transform: rotate(90deg);
}

.rating-modal-close:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.rating-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.15rem 1.25rem 1.35rem;
}

.rating-hero-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.15rem;
    border-radius: 24px;
    background:
        linear-gradient(135deg, rgba(26, 111, 196, 0.16), rgba(23, 184, 144, 0.08)),
        var(--rating-bg-panel);
    border: 1px solid var(--rating-border);
}

.rating-hero-avatar {
    position: relative;
    width: 78px;
    height: 78px;
    min-width: 78px;
    border-radius: 24px;
    padding: 3px;
    background: linear-gradient(135deg, rgba(26, 111, 196, 0.9), rgba(23, 184, 144, 0.82));
    box-shadow: 0 18px 38px rgba(26, 111, 196, 0.22);
}

.rating-doctor-avatar,
.rating-doctor-avatar-fallback {
    width: 100%;
    height: 100%;
    border-radius: 21px;
}

.rating-doctor-avatar {
    object-fit: cover;
    display: block;
    background: #dbeafe;
}

.rating-doctor-avatar.is-hidden {
    display: none;
}

.rating-doctor-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0f766e, #1d4ed8);
    color: #ffffff;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.rating-hero-copy {
    flex: 1;
    min-width: 0;
}

.rating-kicker {
    margin: 0 0 0.35rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--rating-accent);
}

.rating-hero-copy h2 {
    margin: 0;
    color: var(--rating-text-primary);
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.2;
}

.rating-hero-text {
    margin: 0.55rem 0 0.8rem;
    color: var(--rating-text-secondary);
    font-size: 0.95rem;
    line-height: 1.6;
}

.rating-doctor-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    max-width: 100%;
    padding: 0.55rem 0.85rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--rating-border);
    color: var(--rating-text-primary);
    font-weight: 700;
    white-space: nowrap;
}

.rating-doctor-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
}

.rating-divider {
    height: 1px;
    margin: 1.3rem 0;
    background: linear-gradient(90deg, transparent, var(--rating-border-strong), transparent);
}

.rating-stars-section {
    text-align: center;
}

.rating-stars-label {
    margin: 0;
    color: var(--rating-text-primary);
    font-size: 1rem;
    font-weight: 700;
}

.rating-stars {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.85rem;
    margin: 1.15rem 0 0.85rem;
}

.rating-star {
    width: 58px;
    height: 58px;
    border: 0;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(148, 163, 184, 0.12);
    color: rgba(148, 163, 184, 0.85);
    font-size: 1.95rem;
    transition: transform 0.18s ease, box-shadow 0.22s ease, background 0.22s ease, color 0.22s ease;
}

.rating-star i {
    display: block;
}

.rating-star:hover,
.rating-star.hover {
    transform: translateY(-4px) scale(1.08);
    background: rgba(251, 191, 36, 0.16);
    color: var(--rating-warning);
    box-shadow: 0 14px 28px rgba(251, 191, 36, 0.26);
}

.rating-star.selected {
    transform: translateY(-2px);
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.18), rgba(245, 158, 11, 0.28));
    color: var(--rating-warning);
    box-shadow: 0 16px 30px rgba(245, 158, 11, 0.25);
}

.rating-stars-feedback {
    min-height: 24px;
    color: var(--rating-text-secondary);
    font-size: 0.9rem;
    font-weight: 600;
}

.rating-stars-feedback.active {
    color: var(--rating-accent);
}

.rating-review-section {
    margin-top: 1.25rem;
}

.rating-review-label {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    margin-bottom: 0.8rem;
    color: var(--rating-text-primary);
    font-size: 0.94rem;
    font-weight: 700;
}

.rating-review-field {
    padding: 0.9rem;
    border-radius: 22px;
    background: var(--rating-bg-panel);
    border: 1px solid var(--rating-border);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.rating-review-field:focus-within {
    border-color: rgba(26, 111, 196, 0.45);
    box-shadow: 0 14px 34px rgba(26, 111, 196, 0.12);
    transform: translateY(-1px);
}

.rating-review-textarea {
    width: 100%;
    min-height: 120px;
    border: 0;
    resize: none;
    background: transparent;
    color: var(--rating-text-primary);
    font-size: 0.95rem;
    line-height: 1.65;
    font-family: inherit;
}

.rating-review-textarea::placeholder {
    color: var(--rating-text-secondary);
    opacity: 0.8;
}

.rating-review-textarea:focus {
    outline: none;
}

.rating-review-counter {
    margin-top: 0.6rem;
    text-align: right;
    color: var(--rating-text-secondary);
    font-size: 0.75rem;
    font-weight: 700;
}

.rating-modal-footer {
    display: flex;
    gap: 0.9rem;
    padding: 0 1.25rem 1.25rem;
}

.rating-btn-skip,
.rating-btn-submit {
    min-height: 52px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    transition: transform 0.18s ease, box-shadow 0.22s ease, background 0.22s ease, color 0.22s ease;
}

.rating-btn-skip {
    flex: 1;
    border: 1px solid var(--rating-border);
    background: transparent;
    color: var(--rating-text-secondary);
}

.rating-btn-skip:hover:not(:disabled) {
    transform: translateY(-1px);
    color: var(--rating-text-primary);
    background: rgba(148, 163, 184, 0.12);
}

.rating-btn-submit {
    flex: 1.15;
    position: relative;
    overflow: hidden;
    border: 0;
    color: #ffffff;
    background: linear-gradient(135deg, var(--rating-primary), #2b84d8 52%, var(--rating-accent));
    box-shadow: 0 16px 30px rgba(26, 111, 196, 0.3);
}

.rating-btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px rgba(26, 111, 196, 0.38);
}

.rating-btn-submit:disabled,
.rating-btn-skip:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.rating-btn-icon,
.rating-btn-label {
    position: relative;
    z-index: 1;
}

.rating-btn-spinner {
    position: absolute;
    right: 1rem;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
    border-radius: 50%;
    opacity: 0;
    transform: scale(0.7);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.rating-btn-submit.loading .rating-btn-spinner {
    opacity: 1;
    transform: scale(1);
    animation: ratingSpin 0.75s linear infinite;
}

.rating-btn-submit.loading .rating-btn-icon,
.rating-btn-submit.loading .rating-btn-label {
    opacity: 0.8;
}

.rating-loading-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.28);
    backdrop-filter: blur(4px);
    z-index: 2;
}

.rating-spinner-card {
    min-width: 210px;
    text-align: center;
    padding: 1rem 1.1rem;
    border-radius: 18px;
    background: rgba(15, 23, 42, 0.78);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.28);
}

.spinner-ring {
    width: 44px;
    height: 44px;
    margin: 0 auto 0.85rem;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top-color: #ffffff;
    animation: ratingSpin 0.8s linear infinite;
}

@keyframes ratingSpin {
    to {
        transform: rotate(360deg);
    }
}

.rating-spinner-card p {
    margin: 0;
    color: #ffffff;
    font-size: 0.92rem;
    font-weight: 600;
}

.review-alert-popup,
.review-toast-popup {
    border-radius: 20px !important;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18) !important;
}

.review-alert-title,
.review-toast-title {
    font-weight: 700 !important;
}

.review-alert-confirm,
.swal2-popup .review-submit-btn {
    border-radius: 12px !important;
    background: linear-gradient(135deg, var(--rating-primary), var(--rating-accent)) !important;
    box-shadow: 0 12px 22px rgba(26, 111, 196, 0.24) !important;
}

.review-skip-btn {
    border-radius: 12px !important;
}

@media (max-width: 767.98px) {
    .rating-modal-overlay {
        padding: 1rem;
    }

    .rating-modal-content {
        width: 100%;
        border-radius: 24px;
    }

    .rating-hero-card {
        flex-direction: column;
        text-align: center;
    }

    .rating-hero-copy {
        width: 100%;
    }

    .rating-doctor-chip {
        white-space: normal;
        justify-content: center;
    }

    .rating-stars {
        gap: 0.55rem;
    }

    .rating-star {
        width: 50px;
        height: 50px;
        border-radius: 16px;
        font-size: 1.7rem;
    }

    .rating-modal-footer {
        flex-direction: column-reverse;
    }

    .rating-btn-skip,
    .rating-btn-submit {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .rating-modal-header,
    .rating-modal-body,
    .rating-modal-footer {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .rating-modal-body {
        padding-top: 1rem;
        padding-bottom: 1.15rem;
    }

    .rating-modal-badge {
        font-size: 0.72rem;
    }

    .rating-hero-copy h2 {
        font-size: 1.28rem;
    }

    .rating-stars {
        gap: 0.45rem;
    }

    .rating-star {
        width: 44px;
        height: 44px;
        font-size: 1.45rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingModal = document.getElementById('ratingModal');
    if (!ratingModal) {
        return;
    }

    const ratingStarsContainer = document.getElementById('ratingStars');
    const ratingFeedback = document.getElementById('ratingFeedback');
    const ratingReview = document.getElementById('ratingReview');
    const ratingCharCount = document.getElementById('ratingCharCount');
    const ratingDoctorName = document.getElementById('ratingDoctorName');
    const ratingDoctorAvatar = document.getElementById('ratingDoctorAvatar');
    const ratingDoctorAvatarFallback = document.getElementById('ratingDoctorAvatarFallback');
    const btnSubmitRating = document.getElementById('btnSubmitRating');
    const btnSkipRating = document.getElementById('btnSkipRating');
    const btnSkipSubmit = document.getElementById('btnSkipSubmit');
    const ratingLoadingOverlay = document.getElementById('ratingLoadingOverlay');

    let selectedRating = 0;

    ratingStarsContainer.querySelectorAll('.rating-star').forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.value, 10);
            updateStarSelection();
            updateFeedback();
            updateSubmitButton();
        });

        star.addEventListener('mouseenter', function() {
            const hoverValue = parseInt(this.dataset.value, 10);
            ratingStarsContainer.querySelectorAll('.rating-star').forEach((item, index) => {
                item.classList.toggle('hover', index < hoverValue);
            });
        });
    });

    ratingStarsContainer.addEventListener('mouseleave', updateStarSelection);

    ratingReview.addEventListener('input', function() {
        ratingCharCount.textContent = this.value.length;
        autoResizeTextarea();
    });

    [btnSkipRating, btnSkipSubmit].forEach(button => {
        button.addEventListener('click', function() {
            closeRatingModal();
        });
    });

    btnSubmitRating.addEventListener('click', submitRating);

    function updateStarSelection() {
        ratingStarsContainer.querySelectorAll('.rating-star').forEach((star, index) => {
            const isSelected = index < selectedRating;
            star.classList.toggle('selected', isSelected);
            star.classList.toggle('hover', false);
        });
    }

    function updateFeedback() {
        const feedbacks = {
            0: 'Select a rating to continue',
            1: 'We are sorry this visit fell short.',
            2: 'Thanks for sharing. Your feedback matters.',
            3: 'A solid consultation experience.',
            4: 'Great to hear your consultation went well.',
            5: 'Excellent. Thank you for the wonderful feedback.',
        };

        ratingFeedback.textContent = feedbacks[selectedRating] || feedbacks[0];
        ratingFeedback.classList.toggle('active', selectedRating > 0);
    }

    function updateSubmitButton() {
        btnSubmitRating.disabled = selectedRating === 0;
    }

    function autoResizeTextarea() {
        ratingReview.style.height = 'auto';
        ratingReview.style.height = Math.min(ratingReview.scrollHeight, 220) + 'px';
    }

    function buildInitials(name) {
        return (name || 'Doctor')
            .replace(/^dr\.?\s+/i, '')
            .split(' ')
            .filter(Boolean)
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2) || 'DR';
    }

    function setDoctorAvatar(doctorName, doctorAvatarUrl) {
        const initials = buildInitials(doctorName);
        ratingDoctorAvatarFallback.textContent = initials;

        if (doctorAvatarUrl) {
            ratingDoctorAvatar.src = doctorAvatarUrl;
            ratingDoctorAvatar.alt = doctorName;
            ratingDoctorAvatar.classList.remove('is-hidden');
            ratingDoctorAvatarFallback.classList.add('is-hidden');
            return;
        }

        ratingDoctorAvatar.removeAttribute('src');
        ratingDoctorAvatar.classList.add('is-hidden');
        ratingDoctorAvatarFallback.classList.remove('is-hidden');
    }

    function buildAlertOptions(options = {}) {
        return Object.assign({
            customClass: {
                popup: 'review-alert-popup',
                title: 'review-alert-title',
                confirmButton: 'review-alert-confirm',
                cancelButton: 'review-skip-btn',
            },
            buttonsStyling: true,
        }, options);
    }

    function setLoadingState(isLoading) {
        btnSubmitRating.classList.toggle('loading', isLoading);
        btnSubmitRating.disabled = isLoading || selectedRating === 0;
        btnSkipRating.disabled = isLoading;
        btnSkipSubmit.disabled = isLoading;
        ratingLoadingOverlay.style.display = isLoading ? 'flex' : 'none';
    }

    async function submitRating() {
        if (selectedRating === 0) {
            Swal.fire(buildAlertOptions({
                icon: 'error',
                title: 'Select a rating',
                text: 'Please choose a star rating before submitting your review.',
                confirmButtonText: 'OK',
            }));
            return;
        }

        const ratingData = {
            rating: selectedRating,
            review: ratingReview.value.trim() || null,
        };

        setLoadingState(true);

        try {
            const response = await fetch(`/appointments/${APPT_ID}/rate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(ratingData),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Failed to submit rating');
            }

            closeRatingModal({ shouldRedirect: false });

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Review submitted successfully',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'review-toast-popup',
                    title: 'review-toast-title',
                },
            });

            setTimeout(() => {
                window.location.href = APPT_SHOW_URL;
            }, 1100);
        } catch (error) {
            console.error('Rating submission error:', error);
            Swal.fire(buildAlertOptions({
                icon: 'error',
                title: 'Unable to submit review',
                text: error.message,
                confirmButtonText: 'Try Again',
            }));
        } finally {
            setLoadingState(false);
        }
    }

    window.openRatingModal = function(doctorName, doctorAvatarUrl = null) {
        ratingDoctorName.textContent = doctorName;
        setDoctorAvatar(doctorName, doctorAvatarUrl);
        selectedRating = 0;
        ratingReview.value = '';
        ratingCharCount.textContent = '0';
        autoResizeTextarea();
        updateStarSelection();
        updateFeedback();
        updateSubmitButton();
        ratingModal.classList.remove('is-hidden');
        ratingModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    function closeRatingModal(options = {}) {
        const settings = Object.assign({
            shouldRedirect: true,
            delay: 450,
        }, options);

        ratingModal.classList.add('is-hidden');
        ratingModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (settings.shouldRedirect) {
            setTimeout(() => {
                window.location.href = APPT_SHOW_URL;
            }, settings.delay);
        }
    }

    ratingModal.addEventListener('click', function(event) {
        if (event.target === ratingModal && !btnSubmitRating.classList.contains('loading')) {
            closeRatingModal();
        }
    });
});
</script>
