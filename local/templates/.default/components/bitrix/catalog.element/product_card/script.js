(function () {
    'use strict';

    class ProductCard {
        constructor(rootEl) {
            this.root = rootEl;

            const dataEl = document.querySelector('[data-sku-matrix]');
            if (!dataEl) {
                return;
            }

            const data = JSON.parse(dataEl.textContent);
            this.matrix = data.matrix || {};
            this.colors = data.colors || [];
            this.sizes = data.sizes || [];
            this.sessid = data.sessid;

            this.selectedColor = data.defaultColor;
            this.selectedSize = data.defaultSize;

            this.priceEl = this.root.querySelector('[data-price]');
            this.oldPriceEl = this.root.querySelector('[data-old-price]');
            this.availabilityEl = this.root.querySelector('[data-availability]');
            this.selectedColorNameEl = this.root.querySelector('[data-selected-color]');
            this.stickyEl = document.querySelector('[data-sticky]');
            this.stickyPriceEl = document.querySelector('[data-sticky-price]');
            this.stickyImgEl = document.querySelector('.product-card__sticky-img');
            this.mainImgEl = this.root.querySelector('.product-card__slide.is-active') || this.root.querySelector('.product-card__slide');

            this.bindEvents();
            this.initGallery();
            this.updateView();
            this.setupSticky();
        }

        bindEvents() {
            this.root.querySelectorAll('[data-color-id]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (btn.classList.contains('is-disabled')) return;
                    this.selectColor(parseInt(btn.dataset.colorId, 10));
                });
            });

            this.root.querySelectorAll('[data-size-id]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (btn.classList.contains('is-disabled')) return;
                    this.selectSize(parseInt(btn.dataset.sizeId, 10));
                });
            });

            document.querySelectorAll('[data-buy-btn]').forEach((btn) => {
                btn.addEventListener('click', () => this.addToCart(btn));
            });
        }

        selectColor(colorId) {
            const bySize = this.matrix[colorId] || {};
            // Keep current size if available for new color, else pick first available
            if (!bySize[this.selectedSize] || !bySize[this.selectedSize].available) {
                const firstAvailable = Object.keys(bySize).find((id) => bySize[id].available);
                if (firstAvailable) {
                    this.selectedSize = parseInt(firstAvailable, 10);
                }
            }
            this.selectedColor = colorId;
            this.updateView();
        }

        selectSize(sizeId) {
            this.selectedSize = sizeId;
            this.updateView();
        }

        getCurrentOffer() {
            const bySize = this.matrix[this.selectedColor] || {};
            return bySize[this.selectedSize] || null;
        }

        updateView() {
            const offer = this.getCurrentOffer();

            // Price
            if (offer && this.priceEl) {
                this.priceEl.textContent = offer.price_formatted;
            }
            if (this.oldPriceEl) {
                this.oldPriceEl.textContent = offer && offer.old_price_formatted ? offer.old_price_formatted : '';
            }
            if (this.stickyPriceEl && offer) {
                this.stickyPriceEl.textContent = offer.price_formatted;
            }

            // Swap main image if offer has its own
            if (offer && offer.image) {
                if (this.mainImgEl) {
                    this.mainImgEl.src = offer.image;
                }
                if (this.stickyImgEl) {
                    this.stickyImgEl.src = offer.image;
                }
            }

            // Availability
            if (this.availabilityEl) {
                if (offer && offer.available) {
                    this.availabilityEl.textContent = 'В наличии';
                    this.availabilityEl.className = 'product-card__availability is-available';
                } else {
                    this.availabilityEl.textContent = 'Нет в наличии';
                    this.availabilityEl.className = 'product-card__availability is-unavailable';
                }
            }

            // Color swatches
            this.root.querySelectorAll('[data-color-id]').forEach((btn) => {
                const id = parseInt(btn.dataset.colorId, 10);
                const bySize = this.matrix[id] || {};
                const anyAvailable = Object.values(bySize).some((c) => c.available);
                btn.classList.toggle('is-selected', id === this.selectedColor);
                btn.classList.toggle('is-disabled', !anyAvailable);
            });

            // Sizes
            this.root.querySelectorAll('[data-size-id]').forEach((btn) => {
                const id = parseInt(btn.dataset.sizeId, 10);
                const cell = (this.matrix[this.selectedColor] || {})[id];
                btn.classList.toggle('is-selected', id === this.selectedSize);
                btn.classList.toggle('is-disabled', !cell || !cell.available);
            });

            // Selected color name in label
            if (this.selectedColorNameEl) {
                const colorObj = this.colors.find((c) => c.id === this.selectedColor);
                this.selectedColorNameEl.textContent = colorObj ? colorObj.name : '';
            }

            // Buy buttons enabled/disabled
            document.querySelectorAll('[data-buy-btn]').forEach((btn) => {
                btn.disabled = !offer || !offer.available;
            });
        }

        async addToCart(btn) {
            const offer = this.getCurrentOffer();
            if (!offer || !offer.available || btn.disabled) {
                return;
            }

            btn.disabled = true;
            const originalText = this.getBtnText(btn);

            try {
                const formData = new FormData();
                formData.append('offer_id', String(offer.offer_id));
                formData.append('quantity', '1');
                formData.append('sessid', this.sessid);

                const response = await fetch('/local/ajax/add_to_cart.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (data.status === 'ok') {
                    btn.classList.add('is-success');
                    this.setBtnText(btn, 'Добавлено ✓');
                    this.showCartLink();
                    setTimeout(() => {
                        btn.classList.remove('is-success');
                        this.setBtnText(btn, originalText);
                        btn.disabled = false;
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Ошибка');
                }
            } catch (e) {
                btn.classList.add('is-error');
                this.setBtnText(btn, 'Ошибка, повторите');
                setTimeout(() => {
                    btn.classList.remove('is-error');
                    this.setBtnText(btn, originalText);
                    btn.disabled = false;
                }, 1800);
            }
        }

        showCartLink() {
            let toast = document.querySelector('.product-card__toast');
            if (!toast) {
                toast = document.createElement('a');
                toast.className = 'product-card__toast';
                toast.href = '/personal/cart/';
                toast.innerHTML = '<span>Товар в корзине</span><strong>Перейти →</strong>';
                document.body.appendChild(toast);
            }
            toast.classList.add('is-visible');
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                toast.classList.remove('is-visible');
            }, 4000);
        }

        getBtnText(btn) {
            const label = btn.querySelector('.product-card__buy-label');
            return label ? label.textContent : btn.textContent;
        }

        setBtnText(btn, text) {
            const label = btn.querySelector('.product-card__buy-label');
            if (label) {
                label.textContent = text;
            } else {
                btn.textContent = text;
            }
        }

        initGallery() {
            const dots = this.root.querySelectorAll('[data-slide]');
            const slides = this.root.querySelectorAll('.product-card__slide');
            if (!dots.length || !slides.length) {
                return;
            }

            dots.forEach((dot) => {
                dot.addEventListener('click', () => {
                    const idx = parseInt(dot.dataset.slide, 10);
                    slides.forEach((s, i) => s.classList.toggle('is-active', i === idx));
                    dots.forEach((d, i) => d.classList.toggle('is-active', i === idx));
                });
            });

            // Basic swipe support
            let startX = 0;
            let currentIdx = 0;
            const gallery = this.root.querySelector('.product-card__gallery');
            if (!gallery) return;

            gallery.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
            }, { passive: true });

            gallery.addEventListener('touchend', (e) => {
                const diff = e.changedTouches[0].clientX - startX;
                if (Math.abs(diff) < 40) return;

                if (diff < 0 && currentIdx < slides.length - 1) {
                    currentIdx++;
                } else if (diff > 0 && currentIdx > 0) {
                    currentIdx--;
                }

                slides.forEach((s, i) => s.classList.toggle('is-active', i === currentIdx));
                dots.forEach((d, i) => d.classList.toggle('is-active', i === currentIdx));
            }, { passive: true });
        }

        setupSticky() {
            if (!this.stickyEl) return;
            const mainBtn = this.root.querySelector('.product-card__buy');
            if (!mainBtn) return;

            this.stickyEl.hidden = false;

            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        this.stickyEl.classList.toggle('is-visible', !entry.isIntersecting);
                    });
                },
                { rootMargin: '0px 0px -50px 0px' }
            );
            observer.observe(mainBtn);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('.product-card');
        if (root) {
            new ProductCard(root);
        }
    });
})();
