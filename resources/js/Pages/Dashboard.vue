<template>
  <AppLayout>
    <template #title>Dashboard</template>

    <!-- Stat Row -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-ring stat-ring--active">
          <span class="stat-ring-value">{{ stats.clockedIn }}</span>
        </div>
        <div class="stat-detail">
          <span class="stat-label">Clocked In</span>
          <span class="stat-sublabel">of {{ stats.totalEmployees }} employees</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-ring stat-ring--warning">
          <span class="stat-ring-value">{{ stats.overtimeAlerts }}</span>
        </div>
        <div class="stat-detail">
          <span class="stat-label">Overtime Alerts</span>
          <span class="stat-sublabel">approaching 40h this week</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-ring stat-ring--pending">
          <span class="stat-ring-value">{{ stats.pendingApprovals }}</span>
        </div>
        <div class="stat-detail">
          <span class="stat-label">Pending Approvals</span>
          <span class="stat-sublabel">timesheets awaiting review</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-ring stat-ring--unverified">
          <span class="stat-ring-value">{{ stats.unverifiedEntries }}</span>
        </div>
        <div class="stat-detail">
          <span class="stat-label">Unverified</span>
          <span class="stat-sublabel">missing photo verification</span>
        </div>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid-two">
      <!-- Recent Activity -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Today's Activity</h2>
          <span class="panel-count">{{ activity.length }}</span>
        </div>
        <div class="panel-body">
          <div v-if="activity.length === 0" class="empty-state">
            <Clock :size="24" :stroke-width="1.5" />
            <span>No activity yet today</span>
          </div>
          <div v-else class="activity-list">
            <div v-for="event in activity" :key="event.id" class="activity-item">
              <div class="activity-indicator" :class="'activity-indicator--' + event.type" />
              <div class="activity-content">
                <span class="activity-name">{{ event.employee }}</span>
                <span class="activity-action">{{ event.action }}</span>
              </div>
              <span class="activity-time">{{ event.time }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Alerts</h2>
          <span v-if="alerts.length" class="panel-count panel-count--warning">{{ alerts.length }}</span>
        </div>
        <div class="panel-body">
          <div v-if="alerts.length === 0" class="empty-state">
            <Shield :size="24" :stroke-width="1.5" />
            <span>No active alerts</span>
          </div>
          <div v-else class="alert-list">
            <div v-for="alert in alerts" :key="alert.id" class="alert-item" :class="'alert-item--' + alert.severity">
              <component :is="alertIcon(alert.severity)" :size="16" :stroke-width="2" />
              <div class="alert-content">
                <span class="alert-message">{{ alert.message }}</span>
                <span class="alert-meta">{{ alert.meta }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Team Status -->
    <div class="panel" style="margin-top: var(--sp-5)">
      <div class="panel-header">
        <h2 class="panel-title">Team Status</h2>
      </div>
      <div class="panel-body">
        <div v-if="teams.length === 0" class="empty-state">
          <UsersRound :size="24" :stroke-width="1.5" />
          <span>No teams created yet</span>
        </div>
        <div v-else class="team-grid">
          <div v-for="team in teams" :key="team.id" class="team-card">
            <div class="team-card-header">
              <div class="team-color" :style="{ background: team.color || '#555' }" />
              <span class="team-name">{{ team.name }}</span>
            </div>
            <div class="team-stats">
              <div class="team-stat">
                <span class="team-stat-value">{{ team.clockedIn }}</span>
                <span class="team-stat-label">In</span>
              </div>
              <div class="team-stat-divider" />
              <div class="team-stat">
                <span class="team-stat-value">{{ team.onBreak }}</span>
                <span class="team-stat-label">Break</span>
              </div>
              <div class="team-stat-divider" />
              <div class="team-stat">
                <span class="team-stat-value">{{ team.absent }}</span>
                <span class="team-stat-label">Out</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Clock, Shield, AlertTriangle, AlertCircle, Info, UsersRound } from 'lucide-vue-next';

const page = usePage();

// Props from server (with defaults for when no data exists yet)
const stats = computed(() => page.props.stats ?? {
  totalEmployees: 0,
  clockedIn: 0,
  overtimeAlerts: 0,
  pendingApprovals: 0,
  unverifiedEntries: 0,
});

const activity = computed(() => page.props.activity ?? []);
const alerts = computed(() => page.props.alerts ?? []);
const teams = computed(() => page.props.teams ?? []);

function alertIcon(severity) {
  if (severity === 'critical') return AlertCircle;
  if (severity === 'warning') return AlertTriangle;
  return Info;
}
</script>

<style scoped>
/* === Stat Row — geofence ring motif === */
.stat-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--sp-4);
  margin-bottom: var(--sp-5);
}

.stat-card {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  padding: var(--sp-5);
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

/* The geofence ring — signature element */
.stat-ring {
  width: 52px;
  height: 52px;
  border-radius: var(--radius-full);
  border: 2.5px solid var(--chalk-4);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-ring-value {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.stat-ring--active { border-color: var(--go); }
.stat-ring--active .stat-ring-value { color: var(--go); }

.stat-ring--warning { border-color: var(--flag); }
.stat-ring--warning .stat-ring-value { color: var(--flag); }

.stat-ring--pending { border-color: var(--viz); }
.stat-ring--pending .stat-ring-value { color: var(--viz); }

.stat-ring--unverified { border-color: var(--zone); }
.stat-ring--unverified .stat-ring-value { color: var(--zone); }

.stat-detail {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
}

.stat-sublabel {
  font-size: 11px;
  color: var(--chalk-3);
}

/* === Panels === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.panel-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.panel-count {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-3);
  background: var(--slab-3);
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-variant-numeric: tabular-nums;
}

.panel-count--warning {
  color: var(--flag);
  background: var(--flag-soft);
}

.panel-body {
  padding: var(--sp-4) var(--sp-5);
}

/* === Grid === */
.grid-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
}

/* === Empty State === */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-8) 0;
  color: var(--chalk-4);
  font-size: 13px;
}

/* === Activity List === */
.activity-list {
  display: flex;
  flex-direction: column;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) 0;
  border-bottom: 1px solid var(--seam-1);
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-indicator {
  width: 6px;
  height: 6px;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.activity-indicator--clock_in { background: var(--go); }
.activity-indicator--clock_out { background: var(--chalk-3); }
.activity-indicator--break { background: var(--flag); }
.activity-indicator--transfer { background: var(--zone); }

.activity-content {
  flex: 1;
  display: flex;
  gap: var(--sp-2);
  align-items: baseline;
  min-width: 0;
}

.activity-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--chalk-1);
  white-space: nowrap;
}

.activity-action {
  font-size: 12px;
  color: var(--chalk-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.activity-time {
  font-size: 11px;
  color: var(--chalk-3);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* === Alert List === */
.alert-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.alert-item {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border-radius: var(--radius-md);
}

.alert-item--critical {
  background: var(--halt-soft);
  color: var(--halt);
}

.alert-item--warning {
  background: var(--flag-soft);
  color: var(--flag);
}

.alert-item--info {
  background: var(--zone-soft);
  color: var(--zone);
}

.alert-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.alert-message {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-1);
}

.alert-meta {
  font-size: 11px;
  color: var(--chalk-3);
}

/* === Team Grid === */
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--sp-3);
}

.team-card {
  padding: var(--sp-4);
  background: var(--slab-3);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-md);
}

.team-card-header {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}

.team-color {
  width: 10px;
  height: 10px;
  border-radius: var(--radius-full);
}

.team-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
}

.team-stats {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.team-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.team-stat-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--chalk-1);
  font-variant-numeric: tabular-nums;
}

.team-stat-label {
  font-size: 10px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
}

.team-stat-divider {
  width: 1px;
  height: 24px;
  background: var(--seam-2);
}
</style>
