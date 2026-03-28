<template>
  <AppLayout>
    <template #title>Teams</template>

    <div v-if="teams.length === 0" class="panel">
      <div class="panel-body">
        <div class="empty-state">
          <UsersRound :size="24" :stroke-width="1.5" />
          <span>No teams created yet</span>
        </div>
      </div>
    </div>

    <div v-else class="team-grid">
      <div v-for="team in teams" :key="team.id" class="team-card">
        <div class="team-card-top">
          <div class="team-card-header">
            <div class="team-color" :style="{ background: team.color_tag || '#555' }" />
            <span class="team-name">{{ team.name }}</span>
          </div>
          <span class="badge" :class="team.status === 'ACTIVE' ? 'badge--active' : 'badge--archived'">
            {{ team.status }}
          </span>
        </div>

        <div class="team-card-body">
          <div class="team-meta-row">
            <span class="team-meta-label">Lead</span>
            <span class="team-meta-value">{{ team.lead_name || '--' }}</span>
          </div>
          <div class="team-meta-row">
            <span class="team-meta-label">Members</span>
            <span class="team-meta-value team-meta-value--count">{{ team.members_count }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { UsersRound } from 'lucide-vue-next';

const page = usePage();
const teams = page.props.teams ?? [];
</script>

<style scoped>
/* === Panel === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.panel-body {
  padding: var(--sp-4) var(--sp-5);
}

/* === Team Grid === */
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--sp-4);
}

.team-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
  transition: border-color var(--duration) var(--ease);
}

.team-card:hover {
  border-color: var(--seam-2);
}

.team-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.team-card-header {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.team-color {
  width: 12px;
  height: 12px;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.team-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--chalk-1);
}

/* === Team Meta === */
.team-card-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--seam-1);
}

.team-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.team-meta-label {
  font-size: 12px;
  color: var(--chalk-3);
}

.team-meta-value {
  font-size: 13px;
  color: var(--chalk-1);
  font-weight: 500;
}

.team-meta-value--count {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

/* === Badge === */
.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.badge--active {
  background: var(--go-soft);
  color: var(--go);
}

.badge--archived {
  background: var(--flag-soft);
  color: var(--flag);
}

/* === Empty State === */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-12) 0;
  color: var(--chalk-4);
  font-size: 13px;
}
</style>
