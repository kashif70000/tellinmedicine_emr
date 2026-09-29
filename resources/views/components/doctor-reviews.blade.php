{{-- Doctor Reviews Component --}}
<style>
    .reviews-container {
        margin-top: 2rem;
    }

    .reviews-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .reviews-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .rating-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #fef3c7;
        color: #92400e;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
    }

    .rating-badge i {
        font-size: 1.1rem;
    }

    .reviews-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 12px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        display: block;
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        display: block;
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .reviews-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .review-item {
        padding: 1.25rem 1.5rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    .review-item:hover {
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }

    .review-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .review-author {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .review-avatar {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #0d9488);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.85rem;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
    }

    .review-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .review-author-info {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }

    .review-author-name {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.95rem;
    }

    .review-author-date {
        font-size: 0.8rem;
        color: #64748b;
    }

    .review-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .review-stars {
        display: flex;
        align-items: center;
        gap: 2px;
    }

    .review-star {
        font-size: 1rem;
        color: #eab308;
    }

    .review-rating-value {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.9rem;
    }

    .review-text {
        color: #334155;
        line-height: 1.6;
        font-size: 0.95rem;
        margin: 0.75rem 0 0;
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        border-left: 3px solid #2563eb;
    }

    .reviews-empty {
        text-align: center;
        padding: 3rem 1.5rem;
        background: #f8fafc;
        border-radius: 12px;
        border: 2px dashed #cbd5e1;
    }

    .reviews-empty i {
        font-size: 3rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
        display: block;
    }

    .reviews-empty p {
        color: #94a3b8;
        margin: 0;
    }

    .reviews-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .page-link {
        padding: 0.5rem 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.85rem;
    }

    .page-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .page-link.active {
        background: #1e40af;
        color: white;
        border-color: #1e40af;
    }

    .page-link:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .reviews-loading {
        text-align: center;
        padding: 2rem;
        color: #64748b;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #e2e8f0;
        border-top-color: #1e40af;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    :root[data-bs-theme="dark"] .reviews-header {
        border-bottom-color: rgba(159, 172, 191, 0.14);
    }

    :root[data-bs-theme="dark"] .reviews-header h5,
    :root[data-bs-theme="dark"] .stat-value,
    :root[data-bs-theme="dark"] .review-author-name,
    :root[data-bs-theme="dark"] .review-rating-value {
        color: #e6eefc;
    }

    :root[data-bs-theme="dark"] .rating-badge {
        background: rgba(245, 158, 11, 0.18);
        color: #fbbf24;
    }

    :root[data-bs-theme="dark"] .reviews-stats,
    :root[data-bs-theme="dark"] .reviews-empty {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
    }

    :root[data-bs-theme="dark"] .stat-label,
    :root[data-bs-theme="dark"] .review-author-date,
    :root[data-bs-theme="dark"] .review-text,
    :root[data-bs-theme="dark"] .reviews-empty p,
    :root[data-bs-theme="dark"] .reviews-loading {
        color: #9facbf;
    }

    :root[data-bs-theme="dark"] .review-item {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
    }

    :root[data-bs-theme="dark"] .review-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.28);
        border-color: rgba(159, 172, 191, 0.22);
    }

    :root[data-bs-theme="dark"] .reviews-empty i {
        color: rgba(159, 172, 191, 0.55);
    }

    :root[data-bs-theme="dark"] .page-link {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
        color: #cbd5e1;
    }

    :root[data-bs-theme="dark"] .page-link:hover {
        background: rgba(148, 163, 184, 0.12);
        border-color: rgba(159, 172, 191, 0.22);
    }

    :root[data-bs-theme="dark"] .page-link.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }

    :root[data-bs-theme="dark"] .loading-spinner {
        border-color: rgba(159, 172, 191, 0.2);
        border-top-color: #60a5fa;
    }

    @media (max-width: 768px) {
        .reviews-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .reviews-stats {
            grid-template-columns: 1fr;
        }

        .review-header {
            flex-direction: flex-row;
            align-items: center;
        }

        .review-rating {
            margin-top: 0;
        }
    }
</style>

<div class="reviews-container" id="doctorReviewsSection">
    <!-- Header -->
    <div class="reviews-header">
        <h5>
            <i class="ti ti-message-circle-2"></i> Patient Reviews
        </h5>
        <div class="rating-badge" id="ratingBadge">
            <i class="ti ti-star-filled"></i>
            <span id="averageRating">—</span>
            <span id="totalReviews">(0 reviews)</span>
        </div>
    </div>

    <!-- Stats -->
    <div class="reviews-stats" id="reviewsStats">
        <div class="stat-item">
            <span class="stat-value" id="avgRatingValue">—</span>
            <span class="stat-label">Average Rating</span>
        </div>
        <div class="stat-item">
            <span class="stat-value" id="totalReviewsCount">0</span>
            <span class="stat-label">Total Reviews</span>
        </div>
        <div class="stat-item">
            <span class="stat-value" id="fiveStarCount">0</span>
            <span class="stat-label">5-Star Reviews</span>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="reviews-list" id="reviewsList">
        <div class="reviews-empty">
            <i class="ti ti-inbox"></i>
            <p>No reviews yet. Come back after your first consultation!</p>
        </div>
    </div>

    <!-- Pagination -->
    <div class="reviews-pagination" id="reviewsPagination" style="display: none;">
        <!-- Will be populated by JavaScript -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const doctorId = @json($doctorId ?? null);
    const reviewsSection = document.getElementById('doctorReviewsSection');

    if (!doctorId) {
        reviewsSection.style.display = 'none';
        return;
    }

    loadReviews(doctorId, 1);

    async function loadReviews(doctorId, page = 1) {
        try {
            reviewsSection.innerHTML = `
                <div class="reviews-header">
                    <h5><i class="ti ti-message-circle-2"></i> Patient Reviews</h5>
                </div>
                <div class="reviews-loading">
                    <span class="loading-spinner"></span> Loading reviews...
                </div>
            `;

            const response = await fetch(`/doctors/${doctorId}/reviews?page=${page}&limit=10`);
            if (!response.ok) throw new Error('Failed to load reviews');

            const data = await response.json();
            renderReviews(data);
        } catch (error) {
            console.error('Error loading reviews:', error);
            reviewsSection.innerHTML = `
                <div class="reviews-header">
                    <h5><i class="ti ti-message-circle-2"></i> Patient Reviews</h5>
                </div>
                <div class="reviews-empty">
                    <i class="ti ti-alert-circle"></i>
                    <p>Could not load reviews. Please try again later.</p>
                </div>
            `;
        }
    }

    function renderReviews(data) {
        const { average_rating, total_reviews, reviews, pagination } = data;

        // Build rating distribution
        const ratingCounts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
        reviews.forEach(review => {
            if (review.rating >= 1 && review.rating <= 5) {
                ratingCounts[review.rating]++;
            }
        });

        const formattedAvg = average_rating !== null && average_rating !== undefined ? Number(average_rating).toFixed(1) : '—';

        // Render header
        reviewsSection.innerHTML = `
            <div class="reviews-header">
                <h5><i class="ti ti-message-circle-2"></i> Patient Reviews</h5>
                <div class="rating-badge">
                    <i class="ti ti-star-filled"></i>
                    <span>${formattedAvg}</span>
                    <span>(${total_reviews} review${total_reviews !== 1 ? 's' : ''})</span>
                </div>
            </div>

            <div class="reviews-stats">
                <div class="stat-item">
                    <span class="stat-value">${formattedAvg}</span>
                    <span class="stat-label">Average Rating</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${total_reviews}</span>
                    <span class="stat-label">Total Reviews</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${ratingCounts[5]}</span>
                    <span class="stat-label">5-Star Reviews</span>
                </div>
            </div>

            <div class="reviews-list" id="reviewsList">
                ${reviews.length > 0 ? renderReviewItems(reviews) : '<div class="reviews-empty"><i class="ti ti-inbox"></i><p>No reviews yet. Come back after your first consultation!</p></div>'}
            </div>

            ${pagination.total_pages > 1 ? renderPagination(pagination) : ''}
        `;

        if (pagination.total_pages > 1) {
            document.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function() {
                    const page = parseInt(this.dataset.page);
                    loadReviews(doctorId, page);
                    reviewsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }
    }

    function renderReviewItems(reviews) {
        return reviews.map(review => {
            const ratingVal = review.rating ? Number(review.rating).toFixed(1) : '5.0';
            return `
                <div class="review-item">
                    <div class="review-header">
                        <div class="review-author">
                            <div class="review-avatar">${renderPatientAvatar(review.patient?.user)}</div>
                            <div class="review-author-info">
                                <div class="review-author-name">${escapeHtml(review.patient?.user?.name || 'Anonymous')}</div>
                                <div class="review-author-date">${formatDate(review.created_at)}</div>
                            </div>
                        </div>
                        <div class="review-rating">
                            <div class="review-stars">
                                ${renderStars(review.rating)}
                            </div>
                            <div class="review-rating-value">${ratingVal}</div>
                        </div>
                    </div>
                    ${review.review ? `<div class="review-text">${escapeHtml(review.review)}</div>` : ''}
                </div>
            `;
        }).join('');
    }

    function renderPatientAvatar(user) {
        const name = user?.name || 'Patient';
        const hasProfileImage = Boolean(user?.profile_image && user?.profile_image_url);

        if (hasProfileImage) {
            return `<img src="${escapeHtml(user.profile_image_url)}" alt="${escapeHtml(name)}">`;
        }

        return escapeHtml(getInitials(name));
    }

    function renderStars(rating) {
        const num = parseInt(rating, 10) || 5;
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= num) {
                stars += `<i class="ti ti-star-filled review-star"></i>`;
            } else {
                stars += `<i class="ti ti-star review-star" style="opacity: 0.3;"></i>`;
            }
        }
        return stars;
    }

    function renderPagination(pagination) {
        const { current_page, total_pages } = pagination;
        let html = '<div class="reviews-pagination">';

        if (current_page > 1) {
            html += `<button class="page-link" data-page="${current_page - 1}"><i class="ti ti-chevron-left"></i></button>`;
        }

        for (let i = 1; i <= total_pages; i++) {
            if (i === current_page) {
                html += `<span class="page-link active">${i}</span>`;
            } else if (i === 1 || i === total_pages || (i >= current_page - 1 && i <= current_page + 1)) {
                html += `<button class="page-link" data-page="${i}">${i}</button>`;
            } else if (i === 2 || i === total_pages - 1) {
                html += '<span style="padding: 0.5rem 0.25rem;">...</span>';
            }
        }

        if (current_page < total_pages) {
            html += `<button class="page-link" data-page="${current_page + 1}"><i class="ti ti-chevron-right"></i></button>`;
        }

        html += '</div>';
        return html;
    }

    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
        if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
        return `${Math.floor(diffDays / 365)} years ago`;
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});
</script>
