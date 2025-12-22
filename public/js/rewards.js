/**
 * Rewards JavaScript Logic
 * Handles celebration animations and confetti effects
 */
class RewardsManager {
    constructor() {
        this.isActive = false;
        this.canvas = null;
        this.ctx = null;
        this.init();
    }
    /**
     * Initialize Rewards Manager
     */
    init() {
        // Create confetti canvas
        this.createCanvas();
        // Check if rewards are enabled on page load
        this.checkRewardsStatus();
        // Listen for task completion events
        document.addEventListener('task-completed', (event) => {
            this.triggerTaskCompletion(event.detail);
        });
        // Listen for rewards toggle events
        document.addEventListener('rewards-toggled', (event) => {
            this.toggle(event.detail.enabled);
        });
    }
    /**
     * Create confetti canvas
     */
    createCanvas() {
        this.canvas = document.createElement('canvas');
        this.canvas.id = 'confetti-canvas';
        this.canvas.style.position = 'fixed';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.pointerEvents = 'none';
        this.canvas.style.zIndex = '9999';
        this.canvas.style.display = 'none';
        document.body.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d');
        // Resize canvas when window resizes
        window.addEventListener('resize', () => this.resizeCanvas());
        this.resizeCanvas();
    }
    /**
     * Resize canvas to match window size
     */
    resizeCanvas() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }
    /**
     * Check current rewards status from API
     */
    async checkRewardsStatus() {
        try {
            const response = await fetch('/api/v1/app/rewards/status', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.isActive = data.data.rewards_active;
                }
            }
        }
        catch (error) {
            console.error('Error checking rewards status:', error);
        }
    }
    /**
     * Toggle rewards
     */
    async toggle(enabled = null) {
        try {
            const response = await fetch('/api/v1/app/rewards/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.isActive = data.data.rewards_enabled;
                    this.updateToggleButton(this.isActive);
                }
            }
        }
        catch (error) {
            console.error('Error toggling rewards:', error);
        }
    }
    /**
     * Trigger task completion celebration
     */
    async triggerTaskCompletion(taskData) {
        if (!this.isActive) {
            return;
        }
        try {
            const response = await fetch('/api/v1/app/rewards/trigger-task-completion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify(taskData)
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.rewards_triggered) {
                    this.showCelebration(data.data.reward_data);
                }
            }
        }
        catch (error) {
            console.error('Error triggering rewards:', error);
        }
    }
    /**
     * Show celebration animation
     */
    showCelebration(rewardData) {
        // Show confetti animation
        this.showConfetti(rewardData.config);
        // Show congratulatory message
        this.showCongratsMessage(rewardData.messages);
        // Auto-dismiss after duration
        setTimeout(() => {
            this.hideCelebration();
        }, rewardData.duration || 4000);
    }
    /**
     * Show confetti animation
     */
    showConfetti(config = {}) {
        const defaultConfig = {
            particleCount: 100,
            spread: 70,
            startVelocity: 45,
            colors: ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57']
        };
        const confettiConfig = { ...defaultConfig, ...config };
        this.canvas.style.display = 'block';
        // Create confetti particles
        const particles = [];
        for (let i = 0; i < confettiConfig.particleCount; i++) {
            particles.push(this.createParticle(confettiConfig));
        }
        // Animate confetti
        this.animateConfetti(particles);
    }
    /**
     * Create a confetti particle
     */
    createParticle(config) {
        return {
            x: Math.random() * this.canvas.width,
            y: -10,
            vx: (Math.random() - 0.5) * config.spread,
            vy: Math.random() * config.startVelocity + 10,
            color: config.colors[Math.floor(Math.random() * config.colors.length)],
            size: Math.random() * 10 + 5,
            rotation: Math.random() * 360,
            rotationSpeed: (Math.random() - 0.5) * 20,
            gravity: 0.5,
            life: 1.0,
            decay: Math.random() * 0.02 + 0.01
        };
    }
    /**
     * Animate confetti particles
     */
    animateConfetti(particles) {
        const animate = () => {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            for (let i = particles.length - 1; i >= 0; i--) {
                const particle = particles[i];
                // Update particle position
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.vy += particle.gravity;
                particle.rotation += particle.rotationSpeed;
                particle.life -= particle.decay;
                // Draw particle
                this.ctx.save();
                this.ctx.globalAlpha = particle.life;
                this.ctx.translate(particle.x, particle.y);
                this.ctx.rotate(particle.rotation * Math.PI / 180);
                this.ctx.fillStyle = particle.color;
                this.ctx.fillRect(-particle.size / 2, -particle.size / 2, particle.size, particle.size);
                this.ctx.restore();
                // Remove dead particles
                if (particle.life <= 0 || particle.y > this.canvas.height) {
                    particles.splice(i, 1);
                }
            }
            // Continue animation if particles remain
            if (particles.length > 0) {
                requestAnimationFrame(animate);
            }
            else {
                this.canvas.style.display = 'none';
            }
        };
        animate();
    }
    /**
     * Show congratulatory message
     */
    showCongratsMessage(messages) {
        // Create message overlay
        const overlay = document.createElement('div');
        overlay.id = 'rewards-message-overlay';
        overlay.className = 'rewards-message-overlay';
        overlay.innerHTML = `
            <div class="rewards-message">
                <div class="rewards-icon">🎉</div>
                <h2 class="rewards-title">${messages.celebration_title}</h2>
                <p class="rewards-message-text">${messages.congrats_message}</p>
                <p class="rewards-subtitle">${messages.keep_it_up}</p>
            </div>
        `;
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .rewards-message-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease-in;
            }
            
            .rewards-message {
                background: white;
                padding: 2rem;
                border-radius: 1rem;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.5s ease-out;
                max-width: 400px;
                margin: 1rem;
            }
            
            .rewards-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
                animation: bounce 1s ease-in-out infinite;
            }
            
            .rewards-title {
                font-size: 1.5rem;
                font-weight: bold;
                color: #333;
                margin-bottom: 0.5rem;
            }
            
            .rewards-message-text {
                font-size: 1.1rem;
                color: #666;
                margin-bottom: 0.5rem;
            }
            
            .rewards-subtitle {
                font-size: 0.9rem;
                color: #888;
                font-style: italic;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideInUp {
                from { 
                    opacity: 0;
                    transform: translateY(50px);
                }
                to { 
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(overlay);
        // Auto-remove after 4 seconds
        setTimeout(() => {
            overlay.remove();
            style.remove();
        }, 4000);
    }
    /**
     * Hide celebration
     */
    hideCelebration() {
        this.canvas.style.display = 'none';
        const overlay = document.getElementById('rewards-message-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
    /**
     * Update rewards toggle button
     */
    updateToggleButton(enabled) {
        const toggleButton = document.querySelector('[data-rewards-toggle]');
        if (toggleButton) {
            if (enabled) {
                toggleButton.classList.add('active', 'rewards-active');
                toggleButton.setAttribute('aria-pressed', 'true');
                toggleButton.title = 'Disable Rewards';
            }
            else {
                toggleButton.classList.remove('active', 'rewards-active');
                toggleButton.setAttribute('aria-pressed', 'false');
                toggleButton.title = 'Enable Rewards';
            }
        }
    }
    /**
     * Get current rewards state
     */
    getState() {
        return {
            isActive: this.isActive,
            canvasReady: !!this.canvas
        };
    }
}
// Initialize Rewards Manager
const rewardsManager = new RewardsManager();
// Export for use in other scripts
window.RewardsManager = rewardsManager;
// Alpine.js integration
document.addEventListener('alpine:init', () => {
    Alpine.data('rewards', () => ({
        isActive: false,
        init() {
            // Check initial state
            this.isActive = rewardsManager.isActive;
            // Listen for changes
            document.addEventListener('rewards-changed', (event) => {
                this.isActive = event.detail.enabled;
            });
        },
        toggle() {
            rewardsManager.toggle();
        },
        get toggleText() {
            return this.isActive ? 'Disable Rewards' : 'Enable Rewards';
        },
        get toggleIcon() {
            return this.isActive ? 'fas fa-gift' : 'fas fa-gift';
        }
    }));
});
