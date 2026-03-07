<template>
    <div class="bg-white rounded-lg shadow-md dark:bg-gray-800 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-star text-yellow-400 mr-2"></i>
                Scoring
            </h3>
            <div class="flex gap-2">
                <button
                    v-if="currentScore"
                    @click="editCurrentScore"
                    class="text-sm text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                >
                    <i class="fas fa-edit mr-1"></i>
                    Edit
                </button>
                <button
                    v-if="currentScore"
                    @click="deleteCurrentScore"
                    class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                >
                    <i class="fas fa-trash mr-1"></i>
                    Delete
                </button>
            </div>
        </div>

        <!-- Current Score Display -->
        <div v-if="currentScore" class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Current Score</span>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded-full" :class="scoreTypeClass(currentScore.score_type)">
                        {{ scoreTypeLabel(currentScore.score_type) }}
                    </span>
                    <button
                        v-if="subscriptionLevel >= 3 && currentScore.score_type === 'behavior'"
                        @click="recalculateCurrentScore"
                        class="text-xs px-2 py-1 text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"
                        title="Recalculate this score"
                    >
                        <i class="fas fa-sync-alt mr-1"></i>Recalculate
                    </button>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-4xl font-bold text-gray-900 dark:text-white">
                    {{ currentScore.score_value }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    / 100
                </div>
            </div>

            <!-- Score Progress Bar -->
            <div class="mt-3 w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div
                    class="h-2.5 rounded-full transition-all duration-300"
                    :class="scoreColorClass(currentScore.score_value)"
                    :style="`width: ${Math.min(currentScore.score_value, 100)}%`"
                ></div>
            </div>

            <!-- Metadata -->
            <div v-if="currentScore.meta" class="mt-4 space-y-2">
                <div v-if="currentScore.meta.reason" class="text-sm text-gray-600 dark:text-gray-300">
                    <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                    {{ currentScore.meta.reason }}
                </div>

                <div v-if="currentScore.meta.stage" class="text-xs text-gray-500 dark:text-gray-400">
                    Stage: <span class="font-medium">{{ currentScore.meta.stage }}</span>
                </div>

                <div v-if="currentScore.meta.confidence" class="text-xs text-gray-500 dark:text-gray-400">
                    Confidence: <span class="font-medium">{{ formatConfidence(currentScore.meta.confidence) }}%</span>
                </div>
            </div>

            <!-- Breakdown -->
            <div v-if="currentScore.meta && currentScore.meta.breakdown && currentScore.meta.breakdown.length" class="mt-4">
                <button
                    @click="showBreakdown = !showBreakdown"
                    class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 flex items-center gap-1"
                >
                    <i class="fas" :class="showBreakdown ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    Score Breakdown
                </button>

                <div v-if="showBreakdown" class="mt-3 space-y-2">
                    <div
                        v-for="(component, index) in currentScore.meta.breakdown"
                        :key="index"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-600 dark:text-gray-300">{{ component.name }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(component.value) }}</span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="currentScore.notes" class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Notes</div>
                <div class="text-sm text-gray-700 dark:text-gray-200">{{ currentScore.notes }}</div>
            </div>

            <!-- Timestamp -->
            <div class="mt-4 text-xs text-gray-400 dark:text-gray-500">
                Updated {{ formatDate(currentScore.updated_at) }}
                <span v-if="currentScore.user">by {{ currentScore.user.name }}</span>
            </div>

                <button
                    v-if="canAddScore"
                    @click="showAddScoreModal = true"
                    class="btn btn-primary sm mt-4 w-full"
                >
                    <i class="fas fa-plus mr-1"></i>
                    Add Score
                </button>
        </div>

        <!-- Historical Scores (Level 3 only) -->
        <div v-if="subscriptionLevel >= 3 && historicalScores.length > 0" class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                <i class="fas fa-history text-gray-400 mr-2"></i>
                Historical Scores
            </h4>

            <!-- Historical Score Selector -->
            <div class="mb-3">
                <select
                    v-model="selectedHistoricalId"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                >
                    <option :value="null">Select a historical score...</option>
                    <option
                        v-for="score in historicalScores"
                        :key="score.id"
                        :value="score.id"
                    >
                        {{ scoreTypeLabel(score.score_type) }} - {{ score.score_value }}/100 ({{ formatDate(score.created_at) }})
                    </option>
                </select>
            </div>

            <!-- Selected Historical Score Card -->
            <div v-if="selectedHistoricalScore" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs px-2 py-1 rounded-full" :class="scoreTypeClass(selectedHistoricalScore.score_type)">
                        {{ scoreTypeLabel(selectedHistoricalScore.score_type) }}
                    </span>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ selectedHistoricalScore.score_value }}<span class="text-sm text-gray-500">/100</span>
                    </div>
                </div>

                <!-- Historical Metadata -->
                <div v-if="selectedHistoricalScore.meta" class="space-y-2">
                    <div v-if="selectedHistoricalScore.meta.reason" class="text-sm text-gray-600 dark:text-gray-300">
                        <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                        {{ selectedHistoricalScore.meta.reason }}
                    </div>

                    <div v-if="selectedHistoricalScore.meta.stage" class="text-xs text-gray-500 dark:text-gray-400">
                        Stage: <span class="font-medium">{{ selectedHistoricalScore.meta.stage }}</span>
                    </div>

                    <div v-if="selectedHistoricalScore.meta.confidence" class="text-xs text-gray-500 dark:text-gray-400">
                        Confidence: <span class="font-medium">{{ formatConfidence(selectedHistoricalScore.meta.confidence) }}%</span>
                    </div>

                    <!-- Historical Breakdown -->
                    <div v-if="selectedHistoricalScore.meta.breakdown && selectedHistoricalScore.meta.breakdown.length" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Score Breakdown:</div>
                        <div class="space-y-1">
                            <div
                                v-for="(component, index) in selectedHistoricalScore.meta.breakdown"
                                :key="index"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-gray-600 dark:text-gray-300">{{ component.name }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(component.value) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historical Notes -->
                <div v-if="selectedHistoricalScore.notes" class="mt-3 p-2 bg-white dark:bg-gray-800 rounded text-xs">
                    <div class="text-gray-500 dark:text-gray-400 mb-1">Notes:</div>
                    <div class="text-gray-700 dark:text-gray-200">{{ selectedHistoricalScore.notes }}</div>
                </div>

                <!-- Historical Timestamp -->
                <div class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                    Created {{ formatDate(selectedHistoricalScore.created_at) }}
                    <span v-if="selectedHistoricalScore.user">by {{ selectedHistoricalScore.user.name }}</span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!currentScore" class="text-center py-8">
            <i class="fas fa-star text-gray-300 text-4xl mb-3"></i>
            <p class="text-gray-500 dark:text-gray-400">No score yet</p>
            <button
                v-if="canAddScore"
                @click="showAddScoreModal = true"
                class="mt-4 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
            >
                Add First Score
            </button>
        </div>

        <!-- Add/Edit Score Modal -->
        <div v-if="showAddScoreModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ editingScore ? 'Edit Score' : 'Add New Score' }}
                        </h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="submitScore">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Score Type
                                </label>
                                <select v-model="newScore.score_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                    <option value="manual">Manual</option>
                                    <option v-if="subscriptionLevel >= 3" value="behavior">Behavioral (Auto-calculated)</option>
                                </select>
                                <p v-if="subscriptionLevel === 2" class="text-xs text-gray-500 mt-1">
                                    Level 2: Manual scoring only. Upgrade to Level 3 (coming soon) for behavioral scoring.
                                </p>
                            </div>

                            <!-- Behavioral Score Info -->
                            <div v-if="newScore.score_type === 'behavior'" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                                    <div class="text-sm text-blue-700 dark:text-blue-300">
                                        <p class="font-medium mb-1">Automatically Calculated</p>
                                        <p>This score will be calculated based on:</p>
                                        <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
                                            <li>Communications & engagement</li>
                                            <li>Recent activity</li>
                                            <li>Profile completeness</li>
                                            <li>Priority level</li>
                                            <li>Associated deals</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Manual Score Input (only for manual type) -->
                            <div v-if="newScore.score_type === 'manual'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Score Value (0-100)
                                </label>
                                <input
                                    type="number"
                                    v-model="newScore.score_value"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    required
                                />
                            </div>

                            <!-- Reason (only for manual) -->
                            <div v-if="newScore.score_type === 'manual'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Reason
                                </label>
                                <textarea
                                    v-model="newScore.reason"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    placeholder="Why this score?"
                                ></textarea>
                            </div>

                            <!-- Notes (only for manual) -->
                            <div v-if="newScore.score_type === 'manual'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Notes (max 250 characters)
                                </label>
                                <textarea
                                    v-model="newScore.notes"
                                    rows="2"
                                    maxlength="250"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    placeholder="Additional notes..."
                                ></textarea>
                                <div class="text-xs text-gray-500 mt-1">{{ newScore.notes?.length || 0 }}/250</div>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button" @click="closeModal" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                Cancel
                            </button>
                            <button type="submit" :disabled="submitting" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                <span v-if="!submitting">{{ getSubmitButtonText() }}</span>
                                <span v-else><i class="fas fa-spinner fa-spin mr-2"></i>{{ getSubmittingText() }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recalculate Score Modal -->
        <div v-if="showRecalculateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showRecalculateModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-sync-alt text-primary-600 mr-2"></i>
                            Recalculate Behavioral Score
                        </h3>
                        <button @click="showRecalculateModal = false" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="mb-6">
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            How would you like to recalculate this behavioral score?
                        </p>

                        <div class="space-y-3">
                            <!-- Update Current Option -->
                            <button
                                @click="handleRecalculate(true)"
                                :disabled="submitting"
                                class="w-full p-4 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-left transition-colors disabled:opacity-50 disabled:cursor-not-allowed group"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-1">
                                        <i class="fas fa-edit text-xl text-primary-600 group-hover:text-primary-700"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white mb-1">
                                            Update Current Score
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Recalculate and update the existing score value. No historical record will be created.
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <!-- Create New Option -->
                            <button
                                @click="handleRecalculate(false)"
                                :disabled="submitting"
                                class="w-full p-4 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-left transition-colors disabled:opacity-50 disabled:cursor-not-allowed group"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-1">
                                        <i class="fas fa-plus-circle text-xl text-primary-600 group-hover:text-primary-700"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white mb-1">
                                            Create New Score
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Create a new score and move the current score to history. Up to 5 historical scores will be kept.
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                                <div class="text-xs text-blue-700 dark:text-blue-300">
                                    <p class="font-medium">Subscription Level 3 Feature</p>
                                    <p class="mt-1">Historical score tracking allows you to see how a lead's score changes over time. The system automatically maintains up to 5 historical scores per record.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            @click="showRecalculateModal = false"
                            :disabled="submitting"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ScorePanel',
    props: {
        scorableType: {
            type: String,
            required: true
        },
        scorableId: {
            type: Number,
            required: true
        },
        subscriptionLevel: {
            type: Number,
            required: true
        },
        initialScores: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            scores: this.initialScores || [],
            showAddScoreModal: false,
            showRecalculateModal: false,
            showBreakdown: false,
            showHistory: false,
            submitting: false,
            editingScore: null,
            selectedHistoricalId: null,
            newScore: {
                score_type: 'manual',
                score_value: '',
                reason: '',
                notes: ''
            }
        };
    },
    mounted() {

    },
    computed: {
        currentScore() {
            return this.scores.find(s => s.is_current) || null;
        },
        historicalScores() {
            return this.scores.filter(s => !s.is_current).sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        },
        selectedHistoricalScore() {
            if (!this.selectedHistoricalId) return null;
            return this.historicalScores.find(s => s.id === this.selectedHistoricalId) || null;
        },
        canAddScore() {
            if (this.subscriptionLevel === 1) return false;
            if (this.subscriptionLevel === 2) {
                // Level 2: can only have 1 manual score, no behavioral
                return !this.scores.some(s => s.is_current);
            }
            return true; // Level 3+: multiple scores allowed
        }
    },
    methods: {

        editCurrentScore() {
            if (!this.currentScore) return;

            this.editingScore = this.currentScore.id;
            this.newScore = {
                score_type: this.currentScore.score_type,
                score_value: this.currentScore.score_value,
                reason: this.currentScore.meta?.reason || '',
                notes: this.currentScore.notes || ''
            };
            this.showAddScoreModal = true;
        },
        recalculateCurrentScore() {
            if (!this.currentScore || this.currentScore.score_type !== 'behavior') return;

            // Show modal for user to choose update vs create new
            this.showRecalculateModal = true;
        },
        async handleRecalculate(updateCurrent) {
            this.showRecalculateModal = false;
            this.submitting = true;

            try {
                const response = await axios.post('/scores/calculate', {
                    scorable_type: this.scorableType,
                    scorable_id: this.scorableId,
                    update_current: updateCurrent
                });

                if (updateCurrent) {
                    // Update the existing score in the list
                    this.scores = this.scores.map(s =>
                        s.id === response.data.id ? response.data : s
                    );
                    this.showNotification('Score updated successfully', 'success');
                } else {
                    // Add new score and mark old as not current
                    this.scores = this.scores.map(s => {
                        if (s.is_current && s.score_type === 'behavior') {
                            return { ...s, is_current: false };
                        }
                        return s;
                    });
                    this.scores.unshift(response.data);
                    this.showNotification('New score created successfully', 'success');
                }

                // Emit event for parent component
                this.$emit('score-updated', response.data);
            } catch (error) {
                console.error('Error recalculating score:', error);
                this.showNotification(error.response?.data?.message || 'Failed to recalculate score', 'error');
            } finally {
                this.submitting = false;
            }
        },
        async deleteCurrentScore() {
            if (!this.currentScore) return;

            const confirmed = confirm('Are you sure you want to delete this score? This action cannot be undone.');
            if (!confirmed) return;

            this.submitting = true;
            try {
                await axios.delete(`/scores/${this.currentScore.id}`);

                // Remove the score from the list
                this.scores = this.scores.filter(s => s.id !== this.currentScore.id);

                // If there are remaining scores, mark the most recent as current
                if (this.scores.length > 0) {
                    const mostRecent = this.scores.reduce((prev, current) =>
                        new Date(current.created_at) > new Date(prev.created_at) ? current : prev
                    );
                    mostRecent.is_current = true;
                }

                this.showNotification('Score deleted successfully', 'success');

                // Emit event for parent component to update header
                const newCurrent = this.scores.find(s => s.is_current);
                if (newCurrent) {
                    this.$emit('score-updated', newCurrent);
                } else {
                    // No scores left, update header to null
                    this.$emit('score-deleted');
                }
            } catch (error) {
                console.error('Error deleting score:', error);
                this.showNotification(error.response?.data?.message || 'Failed to delete score', 'error');
            } finally {
                this.submitting = false;
            }
        },
        closeModal() {
            this.showAddScoreModal = false;
            this.editingScore = null;
            this.resetForm();
        },
        async submitScore() {
            this.submitting = true;
            try {
                let response;
                const isEditing = !!this.editingScore;

                if (this.newScore.score_type === 'behavior') {
                    // Behavioral score - trigger automatic calculation (for both new and edit)
                    response = await axios.post('/scores/calculate', {
                        scorable_type: this.scorableType,
                        scorable_id: this.scorableId
                    });

                    // Mark ALL other scores as not current before updating
                    this.scores = this.scores.map(s => ({ ...s, is_current: false }));

                    if (isEditing) {
                        // Update the existing score in the list
                        this.scores = this.scores.map(s =>
                            s.id === this.editingScore ? { ...response.data, is_current: true } : s
                        );
                    } else {
                        // Add new score to list with is_current: true
                        this.scores.unshift({ ...response.data, is_current: true });
                    }

                    this.showNotification(isEditing ? 'Score recalculated successfully' : 'Behavioral score calculated successfully', 'success');
                } else if (isEditing) {
                    // Manual score - update existing
                    response = await axios.put(`/scores/${this.editingScore}`, {
                        score_value: this.newScore.score_value,
                        meta: {
                            reason: this.newScore.reason,
                            stage: this.currentScore.meta?.stage || '',
                            model_version: this.currentScore.meta?.model_version || '1.0',
                            auto_generated: false,
                            confidence: this.currentScore.meta?.confidence || null,
                            event_id: this.currentScore.meta?.event_id || null,
                            breakdown: this.currentScore.meta?.breakdown || []
                        },
                        notes: this.newScore.notes
                    });

                    // Update the score in the list
                    this.scores = this.scores.map(s =>
                        s.id === this.editingScore ? response.data : s
                    );

                    this.showNotification('Score updated successfully', 'success');
                } else {
                    // Manual score - create new
                    response = await axios.post('/scores/store', {
                        scorable_type: this.scorableType,
                        scorable_id: this.scorableId,
                        score_type: this.newScore.score_type,
                        score_value: this.newScore.score_value,
                        meta: {
                            reason: this.newScore.reason,
                            stage: '',
                            auto_generated: false
                        },
                        notes: this.newScore.notes
                    });

                    // Mark ALL other scores as not current
                    this.scores = this.scores.map(s => ({ ...s, is_current: false }));

                    // Add new score to list with is_current: true
                    this.scores.unshift({ ...response.data, is_current: true });

                    this.showNotification('Score added successfully', 'success');
                }

                this.closeModal();

                // Emit event for parent component
                this.$emit(isEditing ? 'score-updated' : 'score-added', response.data);
            } catch (error) {
                console.error('Error submitting score:', error);
                this.showNotification(error.response?.data?.message || 'Failed to save score', 'error');
            } finally {
                this.submitting = false;
            }
        },
        resetForm() {
            this.newScore = {
                score_type: 'manual',
                score_value: '',
                reason: '',
                notes: ''
            };
        },
        scoreTypeLabel(type) {
            return type === 'manual' ? 'Manual' : 'Behavioral';
        },
        scoreTypeClass(type) {
            return type === 'manual'
                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        },
        scoreColorClass(value) {
            if (value >= 80) return 'bg-green-500';
            if (value >= 60) return 'bg-blue-500';
            if (value >= 40) return 'bg-yellow-500';
            if (value >= 20) return 'bg-orange-500';
            return 'bg-red-500';
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        formatNumber(value) {
            // Format number to 2 decimal places, but remove trailing zeros
            const num = parseFloat(value);
            if (isNaN(num)) return value;

            // If it's a whole number, return without decimals
            if (num % 1 === 0) return num.toString();

            // Otherwise return with up to 2 decimal places
            return num.toFixed(2).replace(/\.?0+$/, '');
        },
        formatConfidence(value) {
            // Convert 0-1 to percentage and round to whole number
            const percentage = (parseFloat(value) * 100);
            return Math.round(percentage);
        },
        showNotification(message, type = 'success') {
            this.$root.createToast('success', message || 'Success');
        },
        getSubmitButtonText() {
            if (this.newScore.score_type === 'behavior') {
                return this.editingScore ? 'Recalculate Score' : 'Calculate Score';
            }
            return this.editingScore ? 'Update Score' : 'Add Score';
        },
        getSubmittingText() {
            if (this.newScore.score_type === 'behavior') {
                return 'Calculating...';
            }
            return this.editingScore ? 'Updating...' : 'Adding...';
        }
    }
};
</script>
