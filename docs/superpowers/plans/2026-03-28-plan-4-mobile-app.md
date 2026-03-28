# Plan 4: Mobile App (React Native)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a React Native + Expo mobile app with offline-first time tracking via WatermelonDB, GPS geofencing via Transistor Software's `react-native-background-geolocation`, and bidirectional sync with the Laravel backend.

**Architecture:** Expo SDK 55 dev client (not Expo Go — required for native modules). WatermelonDB stores all time entries, breaks, geofences, teams, and jobs locally. The geofence engine runs at the OS level via `react-native-background-geolocation` and triggers clock events even when the app is killed. A sync manager handles bulk push/pull with the Laravel API when connectivity is available. Sanctum bearer tokens stored in `expo-secure-store`.

**Tech Stack:** React Native 0.76, Expo SDK 55, TypeScript 5.x, React Navigation 7.x, WatermelonDB 0.28.x, react-native-background-geolocation 4.x, expo-secure-store, @react-native-community/netinfo, expo-notifications, Zustand 5.x (lightweight state), axios 1.x

---

## File Structure

```
mobile/
├── app.json
├── package.json
├── tsconfig.json
├── babel.config.js
├── metro.config.js
├── eas.json
├── index.ts
├── App.tsx
├── src/
│   ├── config/
│   │   └── api.ts
│   ├── navigation/
│   │   ├── RootNavigator.tsx
│   │   ├── AuthStack.tsx
│   │   └── MainTabs.tsx
│   ├── screens/
│   │   ├── auth/
│   │   │   └── LoginScreen.tsx
│   │   ├── dashboard/
│   │   │   └── DashboardScreen.tsx
│   │   ├── clock/
│   │   │   └── ClockScreen.tsx
│   │   ├── timesheet/
│   │   │   └── TimesheetScreen.tsx
│   │   ├── breaks/
│   │   │   └── BreakScreen.tsx
│   │   └── settings/
│   │       └── SettingsScreen.tsx
│   ├── components/
│   │   ├── ClockButton.tsx
│   │   ├── BreakTimer.tsx
│   │   ├── StatusCard.tsx
│   │   ├── WeeklyHoursBar.tsx
│   │   ├── TimesheetRow.tsx
│   │   └── SyncIndicator.tsx
│   ├── database/
│   │   ├── index.ts
│   │   ├── schema.ts
│   │   ├── migrations.ts
│   │   └── models/
│   │       ├── TimeEntry.ts
│   │       ├── Break.ts
│   │       ├── Geofence.ts
│   │       ├── Team.ts
│   │       ├── Job.ts
│   │       └── Employee.ts
│   ├── services/
│   │   ├── api.ts
│   │   ├── auth.ts
│   │   ├── sync.ts
│   │   ├── geofence.ts
│   │   └── notifications.ts
│   ├── store/
│   │   ├── authStore.ts
│   │   ├── clockStore.ts
│   │   └── syncStore.ts
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useClock.ts
│   │   ├── useSync.ts
│   │   └── useNetworkStatus.ts
│   ├── utils/
│   │   ├── time.ts
│   │   └── location.ts
│   └── types/
│       └── index.ts
├── __tests__/
│   ├── services/
│   │   ├── auth.test.ts
│   │   ├── sync.test.ts
│   │   └── api.test.ts
│   ├── store/
│   │   ├── authStore.test.ts
│   │   └── clockStore.test.ts
│   ├── utils/
│   │   └── time.test.ts
│   └── components/
│       ├── ClockButton.test.tsx
│       └── StatusCard.test.tsx
└── plugins/
    └── withBackgroundGeolocation.js
```

---

## Task 1: Expo Project Scaffold

**Files:**
- Create: `mobile/app.json`
- Create: `mobile/package.json`
- Create: `mobile/tsconfig.json`
- Create: `mobile/babel.config.js`
- Create: `mobile/metro.config.js`
- Create: `mobile/index.ts`
- Create: `mobile/App.tsx`
- Create: `mobile/eas.json`
- Create: `mobile/src/config/api.ts`
- Create: `mobile/src/types/index.ts`

- [ ] **Step 1: Create the Expo project**

Run from project root:

```bash
npx create-expo-app@latest mobile --template blank-typescript
cd mobile
```

Expected: Expo project created with TypeScript template.

- [ ] **Step 2: Install core dependencies**

```bash
cd mobile

npx expo install expo-secure-store expo-notifications expo-device expo-constants expo-linking expo-status-bar @react-native-community/netinfo

npm install @react-navigation/native @react-navigation/bottom-tabs @react-navigation/native-stack react-native-screens react-native-safe-area-context axios zustand @nozbe/watermelondb @nozbe/with-observables react-native-background-geolocation

npm install -D @types/react @types/react-native jest @testing-library/react-native @testing-library/jest-native ts-jest
```

Expected: All packages install without errors.

- [ ] **Step 3: Configure app.json**

```json
{
  "expo": {
    "name": "GeoTime",
    "slug": "geotime",
    "version": "1.0.0",
    "orientation": "portrait",
    "icon": "./assets/icon.png",
    "userInterfaceStyle": "light",
    "newArchEnabled": true,
    "splash": {
      "image": "./assets/splash-icon.png",
      "resizeMode": "contain",
      "backgroundColor": "#1a56db"
    },
    "ios": {
      "supportsTablet": false,
      "bundleIdentifier": "com.geotime.app",
      "infoPlist": {
        "NSLocationAlwaysAndWhenInUseUsageDescription": "GeoTime uses your location to automatically clock you in and out when you arrive at or leave a job site. This is required for accurate time tracking and FLSA compliance.",
        "NSLocationWhenInUseUsageDescription": "GeoTime uses your location to verify your position when you clock in or out.",
        "NSLocationAlwaysUsageDescription": "GeoTime needs background location access to automatically track your work hours at job sites, even when the app is closed.",
        "NSMotionUsageDescription": "GeoTime uses motion data to optimize battery usage while tracking your location at job sites.",
        "UIBackgroundModes": ["location", "fetch", "remote-notification"]
      }
    },
    "android": {
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#1a56db"
      },
      "package": "com.geotime.app",
      "permissions": [
        "ACCESS_FINE_LOCATION",
        "ACCESS_COARSE_LOCATION",
        "ACCESS_BACKGROUND_LOCATION",
        "FOREGROUND_SERVICE",
        "FOREGROUND_SERVICE_LOCATION",
        "POST_NOTIFICATIONS",
        "RECEIVE_BOOT_COMPLETED",
        "ACTIVITY_RECOGNITION"
      ]
    },
    "plugins": [
      "expo-secure-store",
      [
        "expo-notifications",
        {
          "icon": "./assets/notification-icon.png",
          "color": "#1a56db"
        }
      ],
      "./plugins/withBackgroundGeolocation.js"
    ]
  }
}
```

- [ ] **Step 4: Configure TypeScript**

```json
// mobile/tsconfig.json
{
  "extends": "expo/tsconfig.base",
  "compilerOptions": {
    "strict": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"]
    },
    "experimentalDecorators": true
  },
  "include": ["**/*.ts", "**/*.tsx"],
  "exclude": ["node_modules"]
}
```

- [ ] **Step 5: Configure Babel for WatermelonDB**

```js
// mobile/babel.config.js
module.exports = function (api) {
  api.cache(true);
  return {
    presets: ['babel-preset-expo'],
    plugins: [
      ['@babel/plugin-proposal-decorators', { legacy: true }],
      ['module-resolver', {
        root: ['./'],
        alias: {
          '@': './src',
        },
      }],
    ],
  };
};
```

Install the required Babel plugins:

```bash
cd mobile
npm install -D @babel/plugin-proposal-decorators babel-plugin-module-resolver
```

- [ ] **Step 6: Configure Metro for WatermelonDB**

```js
// mobile/metro.config.js
const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// WatermelonDB uses .native.js files
config.resolver.sourceExts = [...config.resolver.sourceExts, 'cjs'];

module.exports = config;
```

- [ ] **Step 7: Create EAS build config**

```json
{
  "cli": {
    "version": ">= 13.0.0"
  },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal",
      "ios": {
        "simulator": true
      }
    },
    "preview": {
      "distribution": "internal"
    },
    "production": {}
  },
  "submit": {
    "production": {}
  }
}
```

- [ ] **Step 8: Create API config**

```ts
// mobile/src/config/api.ts

const ENV = {
  dev: {
    apiUrl: 'http://localhost/api/v1',
  },
  staging: {
    apiUrl: 'https://staging.geotime.app/api/v1',
  },
  prod: {
    apiUrl: 'https://app.geotime.app/api/v1',
  },
};

type Environment = keyof typeof ENV;

const getEnvironment = (): Environment => {
  if (__DEV__) return 'dev';
  // Could use expo-constants to determine staging vs prod
  return 'prod';
};

export const API_CONFIG = ENV[getEnvironment()];
export const API_URL = API_CONFIG.apiUrl;
```

- [ ] **Step 9: Create shared types**

```ts
// mobile/src/types/index.ts

export interface User {
  id: string;
  name: string;
  email: string;
  role: 'employee' | 'team_lead' | 'manager' | 'admin' | 'super_admin';
  tenant_id: string;
}

export interface Tenant {
  id: string;
  name: string;
  plan: 'starter' | 'business';
  status: 'trial' | 'active' | 'past_due' | 'cancelled' | 'suspended';
  timezone: string;
}

export interface AuthResponse {
  data: {
    user: User;
    tenant: Tenant;
    token: string;
  };
}

export interface TimeEntryData {
  id: string;
  employee_id: string;
  job_id: string | null;
  team_id: string | null;
  clock_in: string;
  clock_out: string | null;
  clock_in_lat: number | null;
  clock_in_lng: number | null;
  clock_out_lat: number | null;
  clock_out_lng: number | null;
  clock_method: 'GEOFENCE' | 'MANUAL' | 'KIOSK' | 'ADMIN_OVERRIDE';
  total_hours: number | null;
  status: 'ACTIVE' | 'SUBMITTED' | 'APPROVED' | 'REJECTED' | 'PAYROLL_PROCESSED';
  sync_status: 'pending' | 'synced' | 'conflict';
  notes: string | null;
}

export interface BreakData {
  id: string;
  time_entry_id: string;
  type: 'PAID_REST' | 'UNPAID_MEAL';
  start_time: string;
  end_time: string | null;
  duration_minutes: number | null;
  was_interrupted: boolean;
  sync_status: 'pending' | 'synced' | 'conflict';
}

export interface GeofenceData {
  id: string;
  job_id: string;
  name: string;
  latitude: number;
  longitude: number;
  radius_meters: number;
  is_active: boolean;
}

export interface TeamData {
  id: string;
  name: string;
  color_tag: string;
  status: 'ACTIVE' | 'ARCHIVED';
}

export interface JobData {
  id: string;
  name: string;
  client_name: string;
  address: string;
  status: 'ACTIVE' | 'COMPLETED' | 'ON_HOLD';
}

export interface SyncPayload {
  time_entries: TimeEntryData[];
  breaks: BreakData[];
}

export interface SyncResponse {
  data: {
    geofences: GeofenceData[];
    teams: TeamData[];
    jobs: JobData[];
    confirmed_entries: string[];
    confirmed_breaks: string[];
    conflicts: Array<{
      local_id: string;
      server_id: string;
      type: 'time_entry' | 'break';
    }>;
    server_time: string;
  };
}
```

- [ ] **Step 10: Create entry point and App component**

```ts
// mobile/index.ts
import { registerRootComponent } from 'expo';
import App from './App';

registerRootComponent(App);
```

```tsx
// mobile/App.tsx
import React, { useEffect, useState } from 'react';
import { ActivityIndicator, View, StyleSheet } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { NavigationContainer } from '@react-navigation/native';
import { DatabaseProvider } from '@nozbe/watermelondb/react';

import { database } from '@/database';
import { RootNavigator } from '@/navigation/RootNavigator';
import { useAuthStore } from '@/store/authStore';

export default function App() {
  const [isReady, setIsReady] = useState(false);
  const restoreAuth = useAuthStore((s) => s.restoreAuth);

  useEffect(() => {
    const init = async () => {
      await restoreAuth();
      setIsReady(true);
    };
    init();
  }, []);

  if (!isReady) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color="#1a56db" />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <DatabaseProvider database={database}>
        <NavigationContainer>
          <RootNavigator />
        </NavigationContainer>
      </DatabaseProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  },
});
```

- [ ] **Step 11: Create background geolocation Expo config plugin**

```js
// mobile/plugins/withBackgroundGeolocation.js
const { withInfoPlist, withAndroidManifest } = require('expo/config-plugins');

function withBackgroundGeolocation(config) {
  // iOS: add background modes
  config = withInfoPlist(config, (config) => {
    if (!config.modResults.UIBackgroundModes) {
      config.modResults.UIBackgroundModes = [];
    }
    const modes = config.modResults.UIBackgroundModes;
    if (!modes.includes('location')) modes.push('location');
    if (!modes.includes('fetch')) modes.push('fetch');
    return config;
  });

  // Android: add required permissions and service declarations
  config = withAndroidManifest(config, (config) => {
    const mainApp = config.modResults.manifest.application?.[0];
    if (mainApp) {
      if (!mainApp.service) mainApp.service = [];

      // Add the headless task service for background geolocation
      const hasService = mainApp.service.some(
        (s) => s.$?.['android:name'] === 'com.transistorsoft.locationmanager.HeadlessTask'
      );
      if (!hasService) {
        mainApp.service.push({
          $: {
            'android:name': 'com.transistorsoft.locationmanager.HeadlessTask',
            'android:permission': 'android.permission.BIND_JOB_SERVICE',
            'android:exported': 'false',
          },
        });
      }
    }
    return config;
  });

  return config;
}

module.exports = withBackgroundGeolocation;
```

- [ ] **Step 12: Verify project compiles**

```bash
cd mobile
npx expo doctor
```

Expected: No critical issues. Warnings about native modules requiring dev client are expected.

- [ ] **Step 13: Commit**

```bash
git add mobile/
git commit -m "feat(mobile): scaffold Expo project with TypeScript, core dependencies, and config"
```

---

## Task 2: Navigation Setup

**Files:**
- Create: `mobile/src/navigation/RootNavigator.tsx`
- Create: `mobile/src/navigation/AuthStack.tsx`
- Create: `mobile/src/navigation/MainTabs.tsx`
- Create: `mobile/src/screens/auth/LoginScreen.tsx` (placeholder)
- Create: `mobile/src/screens/dashboard/DashboardScreen.tsx` (placeholder)
- Create: `mobile/src/screens/clock/ClockScreen.tsx` (placeholder)
- Create: `mobile/src/screens/timesheet/TimesheetScreen.tsx` (placeholder)
- Create: `mobile/src/screens/breaks/BreakScreen.tsx` (placeholder)
- Create: `mobile/src/screens/settings/SettingsScreen.tsx` (placeholder)

- [ ] **Step 1: Create AuthStack navigator**

```tsx
// mobile/src/navigation/AuthStack.tsx
import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { LoginScreen } from '@/screens/auth/LoginScreen';

export type AuthStackParamList = {
  Login: undefined;
};

const Stack = createNativeStackNavigator<AuthStackParamList>();

export function AuthStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: '#ffffff' },
      }}
    >
      <Stack.Screen name="Login" component={LoginScreen} />
    </Stack.Navigator>
  );
}
```

- [ ] **Step 2: Create MainTabs navigator**

```tsx
// mobile/src/navigation/MainTabs.tsx
import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { DashboardScreen } from '@/screens/dashboard/DashboardScreen';
import { ClockScreen } from '@/screens/clock/ClockScreen';
import { TimesheetScreen } from '@/screens/timesheet/TimesheetScreen';
import { BreakScreen } from '@/screens/breaks/BreakScreen';
import { SettingsScreen } from '@/screens/settings/SettingsScreen';

export type MainTabsParamList = {
  Dashboard: undefined;
  Clock: undefined;
  Timesheet: undefined;
  Breaks: undefined;
  Settings: undefined;
};

const Tab = createBottomTabNavigator<MainTabsParamList>();

export function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#1a56db' },
        headerTintColor: '#ffffff',
        tabBarActiveTintColor: '#1a56db',
        tabBarInactiveTintColor: '#6b7280',
        tabBarStyle: {
          borderTopColor: '#e5e7eb',
          paddingBottom: 4,
          height: 60,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '600',
        },
      }}
    >
      <Tab.Screen
        name="Dashboard"
        component={DashboardScreen}
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size }) => (
            // Using text as icon placeholder — replace with icon library later
            <TabIcon label="H" color={color} size={size} />
          ),
        }}
      />
      <Tab.Screen
        name="Clock"
        component={ClockScreen}
        options={{
          title: 'Clock',
          tabBarIcon: ({ color, size }) => (
            <TabIcon label="C" color={color} size={size} />
          ),
        }}
      />
      <Tab.Screen
        name="Timesheet"
        component={TimesheetScreen}
        options={{
          title: 'Timesheet',
          tabBarIcon: ({ color, size }) => (
            <TabIcon label="T" color={color} size={size} />
          ),
        }}
      />
      <Tab.Screen
        name="Breaks"
        component={BreakScreen}
        options={{
          title: 'Breaks',
          tabBarIcon: ({ color, size }) => (
            <TabIcon label="B" color={color} size={size} />
          ),
        }}
      />
      <Tab.Screen
        name="Settings"
        component={SettingsScreen}
        options={{
          title: 'Settings',
          tabBarIcon: ({ color, size }) => (
            <TabIcon label="S" color={color} size={size} />
          ),
        }}
      />
    </Tab.Navigator>
  );
}

function TabIcon({ label, color, size }: { label: string; color: string; size: number }) {
  return (
    <React.Fragment>
      {/* Replace with @expo/vector-icons when adding icon library */}
      <import { Text } from 'react-native' />
    </React.Fragment>
  );
}
```

Wait — that TabIcon has a syntax error. Let me write it correctly:

```tsx
// mobile/src/navigation/MainTabs.tsx
import React from 'react';
import { Text, StyleSheet } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { DashboardScreen } from '@/screens/dashboard/DashboardScreen';
import { ClockScreen } from '@/screens/clock/ClockScreen';
import { TimesheetScreen } from '@/screens/timesheet/TimesheetScreen';
import { BreakScreen } from '@/screens/breaks/BreakScreen';
import { SettingsScreen } from '@/screens/settings/SettingsScreen';

export type MainTabsParamList = {
  Dashboard: undefined;
  Clock: undefined;
  Timesheet: undefined;
  Breaks: undefined;
  Settings: undefined;
};

const Tab = createBottomTabNavigator<MainTabsParamList>();

function TabIcon({ label, color }: { label: string; color: string; size: number }) {
  return (
    <Text style={[styles.tabIcon, { color }]}>{label}</Text>
  );
}

export function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#1a56db' },
        headerTintColor: '#ffffff',
        tabBarActiveTintColor: '#1a56db',
        tabBarInactiveTintColor: '#6b7280',
        tabBarStyle: {
          borderTopColor: '#e5e7eb',
          paddingBottom: 4,
          height: 60,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '600',
        },
      }}
    >
      <Tab.Screen
        name="Dashboard"
        component={DashboardScreen}
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size }) => <TabIcon label="H" color={color} size={size} />,
        }}
      />
      <Tab.Screen
        name="Clock"
        component={ClockScreen}
        options={{
          title: 'Clock',
          tabBarIcon: ({ color, size }) => <TabIcon label="C" color={color} size={size} />,
        }}
      />
      <Tab.Screen
        name="Timesheet"
        component={TimesheetScreen}
        options={{
          title: 'Timesheet',
          tabBarIcon: ({ color, size }) => <TabIcon label="T" color={color} size={size} />,
        }}
      />
      <Tab.Screen
        name="Breaks"
        component={BreakScreen}
        options={{
          title: 'Breaks',
          tabBarIcon: ({ color, size }) => <TabIcon label="B" color={color} size={size} />,
        }}
      />
      <Tab.Screen
        name="Settings"
        component={SettingsScreen}
        options={{
          title: 'Settings',
          tabBarIcon: ({ color, size }) => <TabIcon label="S" color={color} size={size} />,
        }}
      />
    </Tab.Navigator>
  );
}

const styles = StyleSheet.create({
  tabIcon: {
    fontSize: 18,
    fontWeight: 'bold',
  },
});
```

- [ ] **Step 3: Create RootNavigator**

```tsx
// mobile/src/navigation/RootNavigator.tsx
import React from 'react';
import { useAuthStore } from '@/store/authStore';
import { AuthStack } from './AuthStack';
import { MainTabs } from './MainTabs';

export function RootNavigator() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  if (!isAuthenticated) {
    return <AuthStack />;
  }

  return <MainTabs />;
}
```

- [ ] **Step 4: Create placeholder screens**

```tsx
// mobile/src/screens/auth/LoginScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function LoginScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>GeoTime</Text>
      <Text style={styles.subtitle}>Login screen placeholder</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#ffffff' },
  title: { fontSize: 32, fontWeight: 'bold', color: '#1a56db' },
  subtitle: { fontSize: 16, color: '#6b7280', marginTop: 8 },
});
```

```tsx
// mobile/src/screens/dashboard/DashboardScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function DashboardScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Dashboard</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
});
```

```tsx
// mobile/src/screens/clock/ClockScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function ClockScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Clock In / Out</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
});
```

```tsx
// mobile/src/screens/timesheet/TimesheetScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function TimesheetScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Timesheet</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
});
```

```tsx
// mobile/src/screens/breaks/BreakScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function BreakScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Breaks</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
});
```

```tsx
// mobile/src/screens/settings/SettingsScreen.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export function SettingsScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Settings</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
});
```

- [ ] **Step 5: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 6: Commit**

```bash
git add mobile/src/navigation/ mobile/src/screens/
git commit -m "feat(mobile): add React Navigation with auth stack and main tab navigator"
```

---

## Task 3: Auth Flow — Login, Token Storage, Auto-Login

**Files:**
- Create: `mobile/src/services/api.ts`
- Create: `mobile/src/services/auth.ts`
- Create: `mobile/src/store/authStore.ts`
- Create: `mobile/src/hooks/useAuth.ts`
- Modify: `mobile/src/screens/auth/LoginScreen.tsx`
- Create: `mobile/__tests__/services/auth.test.ts`
- Create: `mobile/__tests__/store/authStore.test.ts`

- [ ] **Step 1: Create the API client**

```ts
// mobile/src/services/api.ts
import axios, { AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import * as SecureStore from 'expo-secure-store';
import { API_URL } from '@/config/api';

const TOKEN_KEY = 'geotime_auth_token';

const apiClient: AxiosInstance = axios.create({
  baseURL: API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor: attach bearer token
apiClient.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// Response interceptor: handle 401
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      // Auth store will handle redirect via state change
    }
    return Promise.reject(error);
  },
);

export { apiClient, TOKEN_KEY };
```

- [ ] **Step 2: Create the auth service**

```ts
// mobile/src/services/auth.ts
import * as SecureStore from 'expo-secure-store';
import { apiClient, TOKEN_KEY } from './api';
import type { AuthResponse, User, Tenant } from '@/types';

const USER_KEY = 'geotime_user';
const TENANT_KEY = 'geotime_tenant';

export const authService = {
  async login(email: string, password: string, deviceName?: string): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/login', {
      email,
      password,
      device_name: deviceName ?? 'mobile',
    });

    const { token, user, tenant } = response.data.data;

    // Store token securely
    await SecureStore.setItemAsync(TOKEN_KEY, token);
    // Store user and tenant data for offline access
    await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
    await SecureStore.setItemAsync(TENANT_KEY, JSON.stringify(tenant));

    return response.data;
  },

  async logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
    } catch {
      // Ignore network errors on logout — clear local state regardless
    }
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(USER_KEY);
    await SecureStore.deleteItemAsync(TENANT_KEY);
  },

  async getStoredToken(): Promise<string | null> {
    return SecureStore.getItemAsync(TOKEN_KEY);
  },

  async getStoredUser(): Promise<User | null> {
    const raw = await SecureStore.getItemAsync(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as User;
    } catch {
      return null;
    }
  },

  async getStoredTenant(): Promise<Tenant | null> {
    const raw = await SecureStore.getItemAsync(TENANT_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as Tenant;
    } catch {
      return null;
    }
  },

  async fetchMe(): Promise<{ user: User; tenant: Tenant }> {
    const response = await apiClient.get('/auth/me');
    return response.data.data;
  },
};
```

- [ ] **Step 3: Create the auth store (Zustand)**

```ts
// mobile/src/store/authStore.ts
import { create } from 'zustand';
import { authService } from '@/services/auth';
import type { User, Tenant } from '@/types';

interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  tenant: Tenant | null;
  isLoading: boolean;
  error: string | null;

  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  restoreAuth: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  isAuthenticated: false,
  user: null,
  tenant: null,
  isLoading: false,
  error: null,

  login: async (email: string, password: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await authService.login(email, password);
      set({
        isAuthenticated: true,
        user: response.data.user,
        tenant: response.data.tenant,
        isLoading: false,
      });
    } catch (err: any) {
      const message =
        err.response?.data?.message ??
        err.response?.data?.errors?.email?.[0] ??
        'Login failed. Please try again.';
      set({ isLoading: false, error: message });
      throw err;
    }
  },

  logout: async () => {
    await authService.logout();
    set({
      isAuthenticated: false,
      user: null,
      tenant: null,
      error: null,
    });
  },

  restoreAuth: async () => {
    const token = await authService.getStoredToken();
    if (!token) {
      set({ isAuthenticated: false });
      return;
    }

    // Try to validate token with server
    try {
      const { user, tenant } = await authService.fetchMe();
      set({ isAuthenticated: true, user, tenant });
    } catch {
      // If server unreachable, use stored data (offline support)
      const user = await authService.getStoredUser();
      const tenant = await authService.getStoredTenant();
      if (user && tenant) {
        set({ isAuthenticated: true, user, tenant });
      } else {
        set({ isAuthenticated: false });
      }
    }
  },

  clearError: () => set({ error: null }),
}));
```

- [ ] **Step 4: Create the useAuth hook**

```ts
// mobile/src/hooks/useAuth.ts
import { useAuthStore } from '@/store/authStore';

export function useAuth() {
  const { isAuthenticated, user, tenant, isLoading, error, login, logout, clearError } =
    useAuthStore();

  return {
    isAuthenticated,
    user,
    tenant,
    isLoading,
    error,
    login,
    logout,
    clearError,
  };
}
```

- [ ] **Step 5: Build the LoginScreen**

```tsx
// mobile/src/screens/auth/LoginScreen.tsx
import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { useAuth } from '@/hooks/useAuth';

export function LoginScreen() {
  const { login, isLoading, error, clearError } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  const handleLogin = async () => {
    if (!email.trim() || !password.trim()) {
      Alert.alert('Error', 'Please enter both email and password.');
      return;
    }

    try {
      await login(email.trim(), password);
    } catch {
      // Error is set in the store, displayed below
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View style={styles.inner}>
        {/* Logo / Brand */}
        <View style={styles.header}>
          <Text style={styles.logo}>GeoTime</Text>
          <Text style={styles.tagline}>Geofence-Based Time Tracking</Text>
        </View>

        {/* Error banner */}
        {error && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>{error}</Text>
            <TouchableOpacity onPress={clearError}>
              <Text style={styles.errorDismiss}>X</Text>
            </TouchableOpacity>
          </View>
        )}

        {/* Form */}
        <View style={styles.form}>
          <Text style={styles.label}>Email</Text>
          <TextInput
            style={styles.input}
            placeholder="you@company.com"
            placeholderTextColor="#9ca3af"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            value={email}
            onChangeText={setEmail}
            editable={!isLoading}
          />

          <Text style={styles.label}>Password</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter password"
            placeholderTextColor="#9ca3af"
            secureTextEntry
            value={password}
            onChangeText={setPassword}
            editable={!isLoading}
            onSubmitEditing={handleLogin}
          />

          <TouchableOpacity
            style={[styles.button, isLoading && styles.buttonDisabled]}
            onPress={handleLogin}
            disabled={isLoading}
            activeOpacity={0.8}
          >
            {isLoading ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <Text style={styles.buttonText}>Sign In</Text>
            )}
          </TouchableOpacity>
        </View>

        <Text style={styles.footer}>
          Contact your administrator to get your login credentials.
        </Text>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  inner: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 32,
  },
  header: {
    alignItems: 'center',
    marginBottom: 48,
  },
  logo: {
    fontSize: 40,
    fontWeight: '800',
    color: '#1a56db',
    letterSpacing: -1,
  },
  tagline: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  errorBanner: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
    borderWidth: 1,
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
  },
  errorText: {
    color: '#dc2626',
    fontSize: 14,
    flex: 1,
  },
  errorDismiss: {
    color: '#dc2626',
    fontWeight: 'bold',
    fontSize: 16,
    marginLeft: 8,
    paddingHorizontal: 4,
  },
  form: {
    gap: 4,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 4,
    marginTop: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: '#111827',
    backgroundColor: '#f9fafb',
  },
  button: {
    backgroundColor: '#1a56db',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 24,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
  },
  footer: {
    textAlign: 'center',
    color: '#9ca3af',
    fontSize: 12,
    marginTop: 32,
  },
});
```

- [ ] **Step 6: Write auth service tests**

```ts
// mobile/__tests__/services/auth.test.ts
import * as SecureStore from 'expo-secure-store';
import { authService } from '../../src/services/auth';
import { apiClient, TOKEN_KEY } from '../../src/services/api';

jest.mock('expo-secure-store');
jest.mock('../../src/services/api', () => ({
  apiClient: {
    post: jest.fn(),
    get: jest.fn(),
  },
  TOKEN_KEY: 'geotime_auth_token',
}));

const mockSecureStore = SecureStore as jest.Mocked<typeof SecureStore>;
const mockApiClient = apiClient as jest.Mocked<typeof apiClient>;

describe('authService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('login', () => {
    it('should store token and user data on successful login', async () => {
      const mockResponse = {
        data: {
          data: {
            token: 'test-token-123',
            user: { id: '1', name: 'Test User', email: 'test@test.com', role: 'employee', tenant_id: 't1' },
            tenant: { id: 't1', name: 'Test Co', plan: 'starter', status: 'active', timezone: 'UTC' },
          },
        },
      };

      (mockApiClient.post as jest.Mock).mockResolvedValue(mockResponse);

      const result = await authService.login('test@test.com', 'password');

      expect(mockApiClient.post).toHaveBeenCalledWith('/auth/login', {
        email: 'test@test.com',
        password: 'password',
        device_name: 'mobile',
      });

      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(TOKEN_KEY, 'test-token-123');
      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'geotime_user',
        JSON.stringify(mockResponse.data.data.user),
      );
      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'geotime_tenant',
        JSON.stringify(mockResponse.data.data.tenant),
      );

      expect(result.data.token).toBe('test-token-123');
    });
  });

  describe('logout', () => {
    it('should clear stored data even if API call fails', async () => {
      (mockApiClient.post as jest.Mock).mockRejectedValue(new Error('Network error'));

      await authService.logout();

      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith(TOKEN_KEY);
      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith('geotime_user');
      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith('geotime_tenant');
    });
  });

  describe('getStoredToken', () => {
    it('should return stored token', async () => {
      (mockSecureStore.getItemAsync as jest.Mock).mockResolvedValue('stored-token');

      const token = await authService.getStoredToken();
      expect(token).toBe('stored-token');
    });

    it('should return null when no token stored', async () => {
      (mockSecureStore.getItemAsync as jest.Mock).mockResolvedValue(null);

      const token = await authService.getStoredToken();
      expect(token).toBeNull();
    });
  });

  describe('getStoredUser', () => {
    it('should parse and return stored user', async () => {
      const user = { id: '1', name: 'Test', email: 'test@test.com', role: 'employee', tenant_id: 't1' };
      (mockSecureStore.getItemAsync as jest.Mock).mockResolvedValue(JSON.stringify(user));

      const result = await authService.getStoredUser();
      expect(result).toEqual(user);
    });

    it('should return null for invalid JSON', async () => {
      (mockSecureStore.getItemAsync as jest.Mock).mockResolvedValue('invalid-json');

      const result = await authService.getStoredUser();
      expect(result).toBeNull();
    });
  });
});
```

- [ ] **Step 7: Write auth store tests**

```ts
// mobile/__tests__/store/authStore.test.ts
import { useAuthStore } from '../../src/store/authStore';
import { authService } from '../../src/services/auth';

jest.mock('../../src/services/auth');

const mockAuthService = authService as jest.Mocked<typeof authService>;

describe('authStore', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Reset store state
    useAuthStore.setState({
      isAuthenticated: false,
      user: null,
      tenant: null,
      isLoading: false,
      error: null,
    });
  });

  describe('login', () => {
    it('should set authenticated state on successful login', async () => {
      const mockUser = { id: '1', name: 'Test', email: 'test@test.com', role: 'employee' as const, tenant_id: 't1' };
      const mockTenant = { id: 't1', name: 'Test Co', plan: 'starter' as const, status: 'active' as const, timezone: 'UTC' };

      mockAuthService.login.mockResolvedValue({
        data: { user: mockUser, tenant: mockTenant, token: 'token123' },
      });

      await useAuthStore.getState().login('test@test.com', 'password');

      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user).toEqual(mockUser);
      expect(state.tenant).toEqual(mockTenant);
      expect(state.isLoading).toBe(false);
      expect(state.error).toBeNull();
    });

    it('should set error on failed login', async () => {
      mockAuthService.login.mockRejectedValue({
        response: { data: { message: 'Invalid credentials' } },
      });

      await expect(
        useAuthStore.getState().login('test@test.com', 'wrong'),
      ).rejects.toBeDefined();

      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.error).toBe('Invalid credentials');
      expect(state.isLoading).toBe(false);
    });
  });

  describe('restoreAuth', () => {
    it('should restore auth from server when online', async () => {
      const mockUser = { id: '1', name: 'Test', email: 'test@test.com', role: 'employee' as const, tenant_id: 't1' };
      const mockTenant = { id: 't1', name: 'Test Co', plan: 'starter' as const, status: 'active' as const, timezone: 'UTC' };

      mockAuthService.getStoredToken.mockResolvedValue('token123');
      mockAuthService.fetchMe.mockResolvedValue({ user: mockUser, tenant: mockTenant });

      await useAuthStore.getState().restoreAuth();

      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user).toEqual(mockUser);
    });

    it('should restore auth from local storage when offline', async () => {
      const mockUser = { id: '1', name: 'Test', email: 'test@test.com', role: 'employee' as const, tenant_id: 't1' };
      const mockTenant = { id: 't1', name: 'Test Co', plan: 'starter' as const, status: 'active' as const, timezone: 'UTC' };

      mockAuthService.getStoredToken.mockResolvedValue('token123');
      mockAuthService.fetchMe.mockRejectedValue(new Error('Network error'));
      mockAuthService.getStoredUser.mockResolvedValue(mockUser);
      mockAuthService.getStoredTenant.mockResolvedValue(mockTenant);

      await useAuthStore.getState().restoreAuth();

      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user).toEqual(mockUser);
    });

    it('should set unauthenticated when no token exists', async () => {
      mockAuthService.getStoredToken.mockResolvedValue(null);

      await useAuthStore.getState().restoreAuth();

      expect(useAuthStore.getState().isAuthenticated).toBe(false);
    });
  });

  describe('logout', () => {
    it('should clear all auth state', async () => {
      useAuthStore.setState({
        isAuthenticated: true,
        user: { id: '1', name: 'Test', email: 'test@test.com', role: 'employee', tenant_id: 't1' },
        tenant: { id: 't1', name: 'Test Co', plan: 'starter', status: 'active', timezone: 'UTC' },
      });

      await useAuthStore.getState().logout();

      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.user).toBeNull();
      expect(state.tenant).toBeNull();
    });
  });
});
```

- [ ] **Step 8: Run tests**

```bash
cd mobile
npx jest __tests__/services/auth.test.ts __tests__/store/authStore.test.ts
```

Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add mobile/src/services/ mobile/src/store/authStore.ts mobile/src/hooks/useAuth.ts mobile/src/screens/auth/LoginScreen.tsx mobile/__tests__/
git commit -m "feat(mobile): add auth flow with login, token storage, and auto-login"
```

---

## Task 4: WatermelonDB Setup

**Files:**
- Create: `mobile/src/database/schema.ts`
- Create: `mobile/src/database/migrations.ts`
- Create: `mobile/src/database/index.ts`
- Create: `mobile/src/database/models/TimeEntry.ts`
- Create: `mobile/src/database/models/Break.ts`
- Create: `mobile/src/database/models/Geofence.ts`
- Create: `mobile/src/database/models/Team.ts`
- Create: `mobile/src/database/models/Job.ts`
- Create: `mobile/src/database/models/Employee.ts`

- [ ] **Step 1: Define the WatermelonDB schema**

```ts
// mobile/src/database/schema.ts
import { appSchema, tableSchema } from '@nozbe/watermelondb';

export const schema = appSchema({
  version: 1,
  tables: [
    tableSchema({
      name: 'time_entries',
      columns: [
        { name: 'server_id', type: 'string', isOptional: true },
        { name: 'employee_id', type: 'string' },
        { name: 'job_id', type: 'string', isOptional: true },
        { name: 'team_id', type: 'string', isOptional: true },
        { name: 'clock_in', type: 'number' }, // timestamp in ms
        { name: 'clock_out', type: 'number', isOptional: true },
        { name: 'clock_in_lat', type: 'number', isOptional: true },
        { name: 'clock_in_lng', type: 'number', isOptional: true },
        { name: 'clock_out_lat', type: 'number', isOptional: true },
        { name: 'clock_out_lng', type: 'number', isOptional: true },
        { name: 'clock_method', type: 'string' }, // GEOFENCE, MANUAL, KIOSK, ADMIN_OVERRIDE
        { name: 'total_hours', type: 'number', isOptional: true },
        { name: 'status', type: 'string' }, // ACTIVE, SUBMITTED, APPROVED, REJECTED, PAYROLL_PROCESSED
        { name: 'sync_status', type: 'string' }, // pending, synced, conflict
        { name: 'notes', type: 'string', isOptional: true },
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
    tableSchema({
      name: 'breaks',
      columns: [
        { name: 'server_id', type: 'string', isOptional: true },
        { name: 'time_entry_id', type: 'string' },
        { name: 'type', type: 'string' }, // PAID_REST, UNPAID_MEAL
        { name: 'start_time', type: 'number' }, // timestamp in ms
        { name: 'end_time', type: 'number', isOptional: true },
        { name: 'duration_minutes', type: 'number', isOptional: true },
        { name: 'was_interrupted', type: 'boolean' },
        { name: 'sync_status', type: 'string' }, // pending, synced, conflict
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
    tableSchema({
      name: 'geofences',
      columns: [
        { name: 'server_id', type: 'string' },
        { name: 'job_id', type: 'string' },
        { name: 'name', type: 'string' },
        { name: 'latitude', type: 'number' },
        { name: 'longitude', type: 'number' },
        { name: 'radius_meters', type: 'number' },
        { name: 'is_active', type: 'boolean' },
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
    tableSchema({
      name: 'teams',
      columns: [
        { name: 'server_id', type: 'string' },
        { name: 'name', type: 'string' },
        { name: 'color_tag', type: 'string' },
        { name: 'status', type: 'string' }, // ACTIVE, ARCHIVED
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
    tableSchema({
      name: 'jobs',
      columns: [
        { name: 'server_id', type: 'string' },
        { name: 'name', type: 'string' },
        { name: 'client_name', type: 'string' },
        { name: 'address', type: 'string', isOptional: true },
        { name: 'status', type: 'string' }, // ACTIVE, COMPLETED, ON_HOLD
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
    tableSchema({
      name: 'employees',
      columns: [
        { name: 'server_id', type: 'string' },
        { name: 'first_name', type: 'string' },
        { name: 'last_name', type: 'string' },
        { name: 'email', type: 'string' },
        { name: 'role', type: 'string' },
        { name: 'team_id', type: 'string', isOptional: true },
        { name: 'created_at', type: 'number' },
        { name: 'updated_at', type: 'number' },
      ],
    }),
  ],
});
```

- [ ] **Step 2: Define database migrations**

```ts
// mobile/src/database/migrations.ts
import { schemaMigrations } from '@nozbe/watermelondb/Schema/migrations';

export const migrations = schemaMigrations({
  migrations: [
    // Initial schema — version 1. Future migrations go here.
    // Example for version 2:
    // {
    //   toVersion: 2,
    //   steps: [
    //     addColumns({
    //       table: 'time_entries',
    //       columns: [{ name: 'device_id', type: 'string', isOptional: true }],
    //     }),
    //   ],
    // },
  ],
});
```

- [ ] **Step 3: Create TimeEntry model**

```ts
// mobile/src/database/models/TimeEntry.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text, children, relation } from '@nozbe/watermelondb/decorators';

export default class TimeEntry extends Model {
  static table = 'time_entries';

  static associations = {
    breaks: { type: 'has_many' as const, foreignKey: 'time_entry_id' },
  };

  @text('server_id') serverId!: string | null;
  @text('employee_id') employeeId!: string;
  @text('job_id') jobId!: string | null;
  @text('team_id') teamId!: string | null;
  @field('clock_in') clockIn!: number;
  @field('clock_out') clockOut!: number | null;
  @field('clock_in_lat') clockInLat!: number | null;
  @field('clock_in_lng') clockInLng!: number | null;
  @field('clock_out_lat') clockOutLat!: number | null;
  @field('clock_out_lng') clockOutLng!: number | null;
  @text('clock_method') clockMethod!: string;
  @field('total_hours') totalHours!: number | null;
  @text('status') status!: string;
  @text('sync_status') syncStatus!: string;
  @text('notes') notes!: string | null;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;

  @children('breaks') breaks: any;

  /** Check if this entry is currently active (clocked in, not yet clocked out) */
  get isActive(): boolean {
    return this.clockOut === null || this.clockOut === 0;
  }

  /** Calculate duration in hours from clock_in to clock_out (or now) */
  get durationHours(): number {
    const end = this.clockOut || Date.now();
    return (end - this.clockIn) / (1000 * 60 * 60);
  }
}
```

- [ ] **Step 4: Create Break model**

```ts
// mobile/src/database/models/Break.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text, relation } from '@nozbe/watermelondb/decorators';

export default class Break extends Model {
  static table = 'breaks';

  static associations = {
    time_entries: { type: 'belongs_to' as const, key: 'time_entry_id' },
  };

  @text('server_id') serverId!: string | null;
  @text('time_entry_id') timeEntryId!: string;
  @text('type') type!: string; // PAID_REST, UNPAID_MEAL
  @field('start_time') startTime!: number;
  @field('end_time') endTime!: number | null;
  @field('duration_minutes') durationMinutes!: number | null;
  @field('was_interrupted') wasInterrupted!: boolean;
  @text('sync_status') syncStatus!: string;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;

  @relation('time_entries', 'time_entry_id') timeEntry: any;

  /** Check if this break is currently active (started, not yet ended) */
  get isActive(): boolean {
    return this.endTime === null || this.endTime === 0;
  }

  /** Calculate duration in minutes from start to end (or now) */
  get currentDurationMinutes(): number {
    const end = this.endTime || Date.now();
    return (end - this.startTime) / (1000 * 60);
  }
}
```

- [ ] **Step 5: Create Geofence model**

```ts
// mobile/src/database/models/Geofence.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text } from '@nozbe/watermelondb/decorators';

export default class Geofence extends Model {
  static table = 'geofences';

  @text('server_id') serverId!: string;
  @text('job_id') jobId!: string;
  @text('name') name!: string;
  @field('latitude') latitude!: number;
  @field('longitude') longitude!: number;
  @field('radius_meters') radiusMeters!: number;
  @field('is_active') isActive!: boolean;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;
}
```

- [ ] **Step 6: Create Team model**

```ts
// mobile/src/database/models/Team.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text } from '@nozbe/watermelondb/decorators';

export default class Team extends Model {
  static table = 'teams';

  @text('server_id') serverId!: string;
  @text('name') name!: string;
  @text('color_tag') colorTag!: string;
  @text('status') status!: string;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;
}
```

- [ ] **Step 7: Create Job model**

```ts
// mobile/src/database/models/Job.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text } from '@nozbe/watermelondb/decorators';

export default class Job extends Model {
  static table = 'jobs';

  @text('server_id') serverId!: string;
  @text('name') name!: string;
  @text('client_name') clientName!: string;
  @text('address') address!: string | null;
  @text('status') status!: string;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;
}
```

- [ ] **Step 8: Create Employee model**

```ts
// mobile/src/database/models/Employee.ts
import { Model } from '@nozbe/watermelondb';
import { field, date, readonly, text } from '@nozbe/watermelondb/decorators';

export default class Employee extends Model {
  static table = 'employees';

  @text('server_id') serverId!: string;
  @text('first_name') firstName!: string;
  @text('last_name') lastName!: string;
  @text('email') email!: string;
  @text('role') role!: string;
  @text('team_id') teamId!: string | null;
  @readonly @date('created_at') createdAt!: Date;
  @readonly @date('updated_at') updatedAt!: Date;

  get fullName(): string {
    return `${this.firstName} ${this.lastName}`;
  }
}
```

- [ ] **Step 9: Initialize the database**

```ts
// mobile/src/database/index.ts
import { Database } from '@nozbe/watermelondb';
import SQLiteAdapter from '@nozbe/watermelondb/adapters/sqlite';

import { schema } from './schema';
import { migrations } from './migrations';

import TimeEntry from './models/TimeEntry';
import Break from './models/Break';
import Geofence from './models/Geofence';
import Team from './models/Team';
import Job from './models/Job';
import Employee from './models/Employee';

const adapter = new SQLiteAdapter({
  schema,
  migrations,
  jsi: true, // Use JSI for better performance (requires new arch or Hermes)
  onSetUpError: (error) => {
    console.error('WatermelonDB setup error:', error);
  },
});

export const database = new Database({
  adapter,
  modelClasses: [TimeEntry, Break, Geofence, Team, Job, Employee],
});

// Helper to get typed collections
export const timeEntriesCollection = database.get<TimeEntry>('time_entries');
export const breaksCollection = database.get<Break>('breaks');
export const geofencesCollection = database.get<Geofence>('geofences');
export const teamsCollection = database.get<Team>('teams');
export const jobsCollection = database.get<Job>('jobs');
export const employeesCollection = database.get<Employee>('employees');
```

- [ ] **Step 10: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 11: Commit**

```bash
git add mobile/src/database/
git commit -m "feat(mobile): add WatermelonDB schema, migrations, and models for offline storage"
```

---

## Task 5: Background Geolocation Setup

**Files:**
- Create: `mobile/src/services/geofence.ts`
- Create: `mobile/src/utils/location.ts`

- [ ] **Step 1: Create location utilities**

```ts
// mobile/src/utils/location.ts

/**
 * Calculate distance between two coordinates using the Haversine formula.
 * Returns distance in meters.
 */
export function haversineDistance(
  lat1: number,
  lng1: number,
  lat2: number,
  lng2: number,
): number {
  const R = 6371000; // Earth's radius in meters
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

function toRad(deg: number): number {
  return (deg * Math.PI) / 180;
}

/**
 * Check if a coordinate is within a geofence.
 */
export function isWithinGeofence(
  lat: number,
  lng: number,
  geofenceLat: number,
  geofenceLng: number,
  radiusMeters: number,
): boolean {
  return haversineDistance(lat, lng, geofenceLat, geofenceLng) <= radiusMeters;
}

/**
 * Format coordinates for display.
 */
export function formatCoordinates(lat: number, lng: number): string {
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}
```

- [ ] **Step 2: Create the geofence service**

```ts
// mobile/src/services/geofence.ts
import BackgroundGeolocation, {
  Config,
  Location,
  GeofenceEvent,
  GeofencesChangeEvent,
} from 'react-native-background-geolocation';
import { database, timeEntriesCollection, geofencesCollection } from '@/database';
import { useAuthStore } from '@/store/authStore';
import { Q } from '@nozbe/watermelondb';

const GEOLOCATION_CONFIG: Config = {
  // Activity Recognition
  desiredAccuracy: BackgroundGeolocation.DESIRED_ACCURACY_HIGH,
  distanceFilter: 10,
  stopTimeout: 5,
  stationaryRadius: 25,

  // Application config
  debug: __DEV__,
  logLevel: __DEV__
    ? BackgroundGeolocation.LOG_LEVEL_VERBOSE
    : BackgroundGeolocation.LOG_LEVEL_OFF,
  stopOnTerminate: false,
  startOnBoot: true,
  enableHeadless: true,

  // Geofencing config
  geofenceProximityRadius: 1000, // Pre-activate geofences within 1km
  geofenceInitialTriggerEntry: true,

  // Background permissions
  backgroundPermissionRationale: {
    title: 'Allow GeoTime to access your location in the background?',
    message:
      'GeoTime uses your location to automatically clock you in and out at job sites. This requires "Allow all the time" location permission.',
    positiveAction: 'Change to "Allow all the time"',
    negativeAction: 'Cancel',
  },

  // Notification for Android foreground service
  notification: {
    title: 'GeoTime',
    text: 'Tracking job site attendance',
    channelName: 'GeoTime Location',
    smallIcon: 'drawable/ic_notification',
    priority: BackgroundGeolocation.NOTIFICATION_PRIORITY_LOW,
    sticky: true,
  },
};

class GeofenceService {
  private initialized = false;

  /**
   * Initialize the background geolocation plugin.
   * Call once after login.
   */
  async initialize(): Promise<void> {
    if (this.initialized) return;

    // Configure the plugin
    const state = await BackgroundGeolocation.ready(GEOLOCATION_CONFIG);

    // Listen for geofence events
    BackgroundGeolocation.onGeofence(this.onGeofence.bind(this));

    // Listen for location changes (for GPS verification on manual clock)
    BackgroundGeolocation.onLocation(this.onLocation.bind(this));

    // Listen for connectivity changes
    BackgroundGeolocation.onConnectivityChange((event) => {
      console.log('[Geofence] Connectivity changed:', event.connected);
    });

    this.initialized = true;

    // Start tracking if was previously enabled
    if (!state.enabled) {
      await BackgroundGeolocation.start();
    }

    console.log('[Geofence] Initialized. Tracking state:', state.enabled);
  }

  /**
   * Register geofences from local database (synced from server).
   * Called after initial sync and when geofences are updated.
   */
  async registerGeofences(): Promise<void> {
    // Remove all existing geofences first
    await BackgroundGeolocation.removeGeofences();

    // Get all active geofences from WatermelonDB
    const geofences = await geofencesCollection
      .query(Q.where('is_active', true))
      .fetch();

    if (geofences.length === 0) {
      console.log('[Geofence] No active geofences to register.');
      return;
    }

    // Register each geofence with the OS
    const geofenceConfigs = geofences.map((gf) => ({
      identifier: gf.serverId,
      radius: gf.radiusMeters,
      latitude: gf.latitude,
      longitude: gf.longitude,
      notifyOnEntry: true,
      notifyOnExit: true,
      notifyOnDwell: false,
      extras: {
        jobId: gf.jobId,
        name: gf.name,
      },
    }));

    await BackgroundGeolocation.addGeofences(geofenceConfigs);
    console.log(`[Geofence] Registered ${geofenceConfigs.length} geofences.`);
  }

  /**
   * Handle geofence enter/exit events.
   * Creates or closes time entries in WatermelonDB.
   */
  private async onGeofence(event: GeofenceEvent): Promise<void> {
    const { identifier, action, location } = event;
    console.log(`[Geofence] Event: ${action} geofence "${identifier}"`);

    const user = useAuthStore.getState().user;
    if (!user) {
      console.warn('[Geofence] No authenticated user — ignoring event.');
      return;
    }

    const jobId = event.extras?.jobId ?? null;

    if (action === 'ENTER') {
      await this.handleGeofenceEnter(user.id, identifier, jobId, location);
    } else if (action === 'EXIT') {
      await this.handleGeofenceExit(user.id, identifier, location);
    }
  }

  /**
   * Handle entering a geofence — create a new time entry.
   */
  private async handleGeofenceEnter(
    employeeId: string,
    geofenceId: string,
    jobId: string | null,
    location: Location,
  ): Promise<void> {
    // Check if already clocked in (prevent duplicate entries)
    const activeEntries = await timeEntriesCollection
      .query(
        Q.where('employee_id', employeeId),
        Q.where('clock_out', null),
      )
      .fetch();

    if (activeEntries.length > 0) {
      console.log('[Geofence] Already clocked in — skipping ENTER event.');
      return;
    }

    await database.write(async () => {
      await timeEntriesCollection.create((entry) => {
        entry.employeeId = employeeId;
        entry.jobId = jobId;
        entry.clockIn = Date.now();
        entry.clockInLat = location.coords.latitude;
        entry.clockInLng = location.coords.longitude;
        entry.clockMethod = 'GEOFENCE';
        entry.status = 'ACTIVE';
        entry.syncStatus = 'pending';
      });
    });

    console.log('[Geofence] Auto clock-in created.');
  }

  /**
   * Handle exiting a geofence — close the active time entry.
   */
  private async handleGeofenceExit(
    employeeId: string,
    geofenceId: string,
    location: Location,
  ): Promise<void> {
    const activeEntries = await timeEntriesCollection
      .query(
        Q.where('employee_id', employeeId),
        Q.where('clock_out', null),
      )
      .fetch();

    if (activeEntries.length === 0) {
      console.log('[Geofence] No active entry to clock out — skipping EXIT event.');
      return;
    }

    const entry = activeEntries[0];
    const now = Date.now();

    await database.write(async () => {
      await entry.update((e) => {
        e.clockOut = now;
        e.clockOutLat = location.coords.latitude;
        e.clockOutLng = location.coords.longitude;
        e.totalHours = (now - e.clockIn) / (1000 * 60 * 60);
        e.syncStatus = 'pending';
      });
    });

    console.log('[Geofence] Auto clock-out recorded.');
  }

  /**
   * Handle location updates (used for GPS verification).
   */
  private onLocation(location: Location): void {
    // Location updates are used for real-time position display
    // and GPS verification during manual clock events.
    // Heavy processing is intentionally avoided here for battery.
    if (__DEV__) {
      console.log('[Geofence] Location update:', location.coords.latitude, location.coords.longitude);
    }
  }

  /**
   * Get current location (for manual clock-in/out GPS capture).
   */
  async getCurrentLocation(): Promise<Location> {
    return BackgroundGeolocation.getCurrentPosition({
      timeout: 30,
      maximumAge: 5000,
      desiredAccuracy: 10,
      extras: { purpose: 'manual_clock' },
    });
  }

  /**
   * Stop all tracking (on logout).
   */
  async stopTracking(): Promise<void> {
    await BackgroundGeolocation.removeGeofences();
    await BackgroundGeolocation.stop();
    this.initialized = false;
    console.log('[Geofence] Tracking stopped.');
  }

  /**
   * Get current tracking state.
   */
  async getState() {
    return BackgroundGeolocation.getState();
  }
}

export const geofenceService = new GeofenceService();
```

- [ ] **Step 3: Write location utility tests**

```ts
// mobile/__tests__/utils/time.test.ts
// (We'll add time tests here too — see Task 8)

// mobile/__tests__/services/api.test.ts
import { haversineDistance, isWithinGeofence, formatCoordinates } from '../../src/utils/location';

describe('location utilities', () => {
  describe('haversineDistance', () => {
    it('should calculate distance between two known points', () => {
      // New York to Los Angeles: ~3944 km
      const distance = haversineDistance(40.7128, -74.006, 34.0522, -118.2437);
      expect(distance).toBeGreaterThan(3900000);
      expect(distance).toBeLessThan(4000000);
    });

    it('should return 0 for same point', () => {
      const distance = haversineDistance(40.7128, -74.006, 40.7128, -74.006);
      expect(distance).toBe(0);
    });
  });

  describe('isWithinGeofence', () => {
    const centerLat = 40.7128;
    const centerLng = -74.006;
    const radius = 100; // 100 meters

    it('should return true for point at center', () => {
      expect(isWithinGeofence(centerLat, centerLng, centerLat, centerLng, radius)).toBe(true);
    });

    it('should return false for point far away', () => {
      expect(isWithinGeofence(41.0, -74.0, centerLat, centerLng, radius)).toBe(false);
    });
  });

  describe('formatCoordinates', () => {
    it('should format coordinates with 6 decimal places', () => {
      expect(formatCoordinates(40.7128, -74.006)).toBe('40.712800, -74.006000');
    });
  });
});
```

- [ ] **Step 4: Verify with a manual test on device**

Build the development client:

```bash
cd mobile
npx expo prebuild
npx expo run:ios
# or
npx expo run:android
```

Expected: App builds and launches. Background geolocation plugin initializes without crash. Check console for `[Geofence] Initialized` log.

**Manual verification checklist:**
- App requests location permissions on first launch
- "Allow all the time" permission can be granted (iOS requires settings navigation)
- Android shows foreground service notification
- No crash on permission grant/deny

- [ ] **Step 5: Commit**

```bash
git add mobile/src/services/geofence.ts mobile/src/utils/location.ts mobile/__tests__/
git commit -m "feat(mobile): add background geolocation service with geofence enter/exit handlers"
```

---

## Task 6: Geofence Engine — Register & Handle Events

**Files:**
- Create: `mobile/src/store/clockStore.ts`
- Create: `mobile/src/hooks/useClock.ts`
- Create: `mobile/__tests__/store/clockStore.test.ts`

- [ ] **Step 1: Create the clock store**

```ts
// mobile/src/store/clockStore.ts
import { create } from 'zustand';
import { database, timeEntriesCollection, breaksCollection } from '@/database';
import { Q } from '@nozbe/watermelondb';
import { geofenceService } from '@/services/geofence';
import { useAuthStore } from './authStore';
import type TimeEntry from '@/database/models/TimeEntry';
import type BreakModel from '@/database/models/Break';

interface ClockState {
  isClockedIn: boolean;
  activeEntry: TimeEntry | null;
  activeBreak: BreakModel | null;
  currentLat: number | null;
  currentLng: number | null;
  isLoading: boolean;
  error: string | null;

  loadCurrentStatus: () => Promise<void>;
  manualClockIn: (jobId?: string) => Promise<void>;
  manualClockOut: () => Promise<void>;
  startBreak: (type: 'PAID_REST' | 'UNPAID_MEAL') => Promise<void>;
  endBreak: () => Promise<void>;
  clearError: () => void;
}

export const useClockStore = create<ClockState>((set, get) => ({
  isClockedIn: false,
  activeEntry: null,
  activeBreak: null,
  currentLat: null,
  currentLng: null,
  isLoading: false,
  error: null,

  loadCurrentStatus: async () => {
    const user = useAuthStore.getState().user;
    if (!user) return;

    try {
      const activeEntries = await timeEntriesCollection
        .query(
          Q.where('employee_id', user.id),
          Q.where('clock_out', null),
        )
        .fetch();

      if (activeEntries.length > 0) {
        const entry = activeEntries[0];

        // Check for active break
        const activeBreaks = await breaksCollection
          .query(
            Q.where('time_entry_id', entry.id),
            Q.where('end_time', null),
          )
          .fetch();

        set({
          isClockedIn: true,
          activeEntry: entry,
          activeBreak: activeBreaks.length > 0 ? activeBreaks[0] : null,
        });
      } else {
        set({
          isClockedIn: false,
          activeEntry: null,
          activeBreak: null,
        });
      }
    } catch (err: any) {
      console.error('[ClockStore] Error loading status:', err);
    }
  },

  manualClockIn: async (jobId?: string) => {
    const user = useAuthStore.getState().user;
    if (!user) return;

    set({ isLoading: true, error: null });

    try {
      // Get current GPS position
      const location = await geofenceService.getCurrentLocation();

      await database.write(async () => {
        const entry = await timeEntriesCollection.create((e) => {
          e.employeeId = user.id;
          e.jobId = jobId ?? null;
          e.clockIn = Date.now();
          e.clockInLat = location.coords.latitude;
          e.clockInLng = location.coords.longitude;
          e.clockMethod = 'MANUAL';
          e.status = 'ACTIVE';
          e.syncStatus = 'pending';
        });

        set({
          isClockedIn: true,
          activeEntry: entry,
          currentLat: location.coords.latitude,
          currentLng: location.coords.longitude,
          isLoading: false,
        });
      });
    } catch (err: any) {
      set({
        isLoading: false,
        error: err.message ?? 'Failed to clock in. Please try again.',
      });
    }
  },

  manualClockOut: async () => {
    const { activeEntry } = get();
    if (!activeEntry) return;

    set({ isLoading: true, error: null });

    try {
      // End any active break first
      const { activeBreak } = get();
      if (activeBreak) {
        await database.write(async () => {
          await activeBreak.update((b) => {
            b.endTime = Date.now();
            b.durationMinutes = Math.round(
              (Date.now() - b.startTime) / (1000 * 60),
            );
            b.syncStatus = 'pending';
          });
        });
      }

      // Get current GPS position
      const location = await geofenceService.getCurrentLocation();
      const now = Date.now();

      await database.write(async () => {
        await activeEntry.update((e) => {
          e.clockOut = now;
          e.clockOutLat = location.coords.latitude;
          e.clockOutLng = location.coords.longitude;
          e.totalHours = (now - e.clockIn) / (1000 * 60 * 60);
          e.syncStatus = 'pending';
        });
      });

      set({
        isClockedIn: false,
        activeEntry: null,
        activeBreak: null,
        currentLat: location.coords.latitude,
        currentLng: location.coords.longitude,
        isLoading: false,
      });
    } catch (err: any) {
      set({
        isLoading: false,
        error: err.message ?? 'Failed to clock out. Please try again.',
      });
    }
  },

  startBreak: async (type: 'PAID_REST' | 'UNPAID_MEAL') => {
    const { activeEntry, activeBreak } = get();
    if (!activeEntry || activeBreak) return;

    set({ isLoading: true, error: null });

    try {
      await database.write(async () => {
        const breakRecord = await breaksCollection.create((b) => {
          b.timeEntryId = activeEntry.id;
          b.type = type;
          b.startTime = Date.now();
          b.wasInterrupted = false;
          b.syncStatus = 'pending';
        });

        set({
          activeBreak: breakRecord,
          isLoading: false,
        });
      });
    } catch (err: any) {
      set({
        isLoading: false,
        error: err.message ?? 'Failed to start break.',
      });
    }
  },

  endBreak: async () => {
    const { activeBreak } = get();
    if (!activeBreak) return;

    set({ isLoading: true, error: null });

    try {
      const now = Date.now();
      await database.write(async () => {
        await activeBreak.update((b) => {
          b.endTime = now;
          b.durationMinutes = Math.round((now - b.startTime) / (1000 * 60));
          b.syncStatus = 'pending';
        });
      });

      set({
        activeBreak: null,
        isLoading: false,
      });
    } catch (err: any) {
      set({
        isLoading: false,
        error: err.message ?? 'Failed to end break.',
      });
    }
  },

  clearError: () => set({ error: null }),
}));
```

- [ ] **Step 2: Create the useClock hook**

```ts
// mobile/src/hooks/useClock.ts
import { useEffect } from 'react';
import { useClockStore } from '@/store/clockStore';

export function useClock() {
  const store = useClockStore();

  useEffect(() => {
    store.loadCurrentStatus();
  }, []);

  return store;
}
```

- [ ] **Step 3: Write clock store tests**

```ts
// mobile/__tests__/store/clockStore.test.ts
import { useClockStore } from '../../src/store/clockStore';

// Mock dependencies
jest.mock('../../src/database', () => ({
  database: {
    write: jest.fn((fn) => fn()),
  },
  timeEntriesCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
    })),
    create: jest.fn(),
  },
  breaksCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
    })),
    create: jest.fn(),
  },
}));

jest.mock('../../src/services/geofence', () => ({
  geofenceService: {
    getCurrentLocation: jest.fn().mockResolvedValue({
      coords: { latitude: 40.7128, longitude: -74.006 },
    }),
  },
}));

jest.mock('../../src/store/authStore', () => ({
  useAuthStore: {
    getState: () => ({
      user: { id: 'user-1', name: 'Test User', email: 'test@test.com', role: 'employee', tenant_id: 't1' },
    }),
  },
}));

describe('clockStore', () => {
  beforeEach(() => {
    useClockStore.setState({
      isClockedIn: false,
      activeEntry: null,
      activeBreak: null,
      currentLat: null,
      currentLng: null,
      isLoading: false,
      error: null,
    });
  });

  describe('initial state', () => {
    it('should start with not clocked in', () => {
      const state = useClockStore.getState();
      expect(state.isClockedIn).toBe(false);
      expect(state.activeEntry).toBeNull();
      expect(state.activeBreak).toBeNull();
      expect(state.isLoading).toBe(false);
      expect(state.error).toBeNull();
    });
  });

  describe('clearError', () => {
    it('should clear the error state', () => {
      useClockStore.setState({ error: 'Test error' });
      useClockStore.getState().clearError();
      expect(useClockStore.getState().error).toBeNull();
    });
  });
});
```

- [ ] **Step 4: Run tests**

```bash
cd mobile
npx jest __tests__/store/clockStore.test.ts
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add mobile/src/store/clockStore.ts mobile/src/hooks/useClock.ts mobile/__tests__/store/clockStore.test.ts
git commit -m "feat(mobile): add clock store with manual clock in/out and break management"
```

---

## Task 7: Clock In/Out UI

**Files:**
- Modify: `mobile/src/screens/clock/ClockScreen.tsx`
- Create: `mobile/src/components/ClockButton.tsx`
- Create: `mobile/src/components/StatusCard.tsx`
- Create: `mobile/src/utils/time.ts`
- Create: `mobile/__tests__/utils/time.test.ts`
- Create: `mobile/__tests__/components/ClockButton.test.tsx`

- [ ] **Step 1: Create time utilities**

```ts
// mobile/src/utils/time.ts

/**
 * Format a timestamp (ms) to a human-readable time string (HH:MM AM/PM).
 */
export function formatTime(timestamp: number): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format a duration in hours to "Xh Ym" string.
 */
export function formatDuration(hours: number): string {
  if (hours < 0) return '0h 0m';
  const h = Math.floor(hours);
  const m = Math.round((hours - h) * 60);
  if (h === 0) return `${m}m`;
  if (m === 0) return `${h}h`;
  return `${h}h ${m}m`;
}

/**
 * Format a duration in minutes to "Xm" or "Xh Ym".
 */
export function formatMinutes(minutes: number): string {
  if (minutes < 60) return `${Math.round(minutes)}m`;
  const h = Math.floor(minutes / 60);
  const m = Math.round(minutes % 60);
  return `${h}h ${m}m`;
}

/**
 * Get the start of today as a timestamp (ms).
 */
export function startOfToday(): number {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  return now.getTime();
}

/**
 * Get the start of the week (Monday) as a timestamp (ms).
 */
export function startOfWeek(weekStartDay: number = 1): number {
  const now = new Date();
  const day = now.getDay();
  const diff = (day - weekStartDay + 7) % 7;
  now.setDate(now.getDate() - diff);
  now.setHours(0, 0, 0, 0);
  return now.getTime();
}

/**
 * Format a date from timestamp to YYYY-MM-DD.
 */
export function formatDate(timestamp: number): string {
  return new Date(timestamp).toISOString().split('T')[0];
}

/**
 * Format a date from timestamp to "Mon, Jan 1".
 */
export function formatDateShort(timestamp: number): string {
  return new Date(timestamp).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Calculate total hours from an array of time entries.
 */
export function calculateTotalHours(
  entries: Array<{ clockIn: number; clockOut: number | null }>,
): number {
  return entries.reduce((total, entry) => {
    const end = entry.clockOut ?? Date.now();
    return total + (end - entry.clockIn) / (1000 * 60 * 60);
  }, 0);
}
```

- [ ] **Step 2: Write time utility tests**

```ts
// mobile/__tests__/utils/time.test.ts
import {
  formatTime,
  formatDuration,
  formatMinutes,
  formatDate,
  calculateTotalHours,
  startOfWeek,
} from '../../src/utils/time';

describe('time utilities', () => {
  describe('formatDuration', () => {
    it('should format whole hours', () => {
      expect(formatDuration(8)).toBe('8h');
    });

    it('should format hours and minutes', () => {
      expect(formatDuration(8.5)).toBe('8h 30m');
    });

    it('should format minutes only', () => {
      expect(formatDuration(0.25)).toBe('15m');
    });

    it('should handle zero', () => {
      expect(formatDuration(0)).toBe('0m');
    });

    it('should handle negative values', () => {
      expect(formatDuration(-1)).toBe('0h 0m');
    });
  });

  describe('formatMinutes', () => {
    it('should format minutes under 60', () => {
      expect(formatMinutes(45)).toBe('45m');
    });

    it('should format minutes over 60', () => {
      expect(formatMinutes(90)).toBe('1h 30m');
    });
  });

  describe('formatDate', () => {
    it('should format to YYYY-MM-DD', () => {
      const ts = new Date('2026-03-28T12:00:00Z').getTime();
      expect(formatDate(ts)).toBe('2026-03-28');
    });
  });

  describe('calculateTotalHours', () => {
    it('should sum up completed entries', () => {
      const entries = [
        { clockIn: 1000000, clockOut: 1000000 + 3600000 }, // 1 hour
        { clockIn: 2000000, clockOut: 2000000 + 7200000 }, // 2 hours
      ];
      expect(calculateTotalHours(entries)).toBe(3);
    });

    it('should handle empty array', () => {
      expect(calculateTotalHours([])).toBe(0);
    });
  });

  describe('startOfWeek', () => {
    it('should return a timestamp', () => {
      const result = startOfWeek(1);
      expect(typeof result).toBe('number');
      expect(result).toBeLessThanOrEqual(Date.now());
    });
  });
});
```

- [ ] **Step 3: Create the ClockButton component**

```tsx
// mobile/src/components/ClockButton.tsx
import React from 'react';
import {
  TouchableOpacity,
  Text,
  StyleSheet,
  ActivityIndicator,
  View,
} from 'react-native';

interface ClockButtonProps {
  isClockedIn: boolean;
  isLoading: boolean;
  onPress: () => void;
  disabled?: boolean;
}

export function ClockButton({ isClockedIn, isLoading, onPress, disabled }: ClockButtonProps) {
  return (
    <TouchableOpacity
      style={[
        styles.button,
        isClockedIn ? styles.clockOut : styles.clockIn,
        (isLoading || disabled) && styles.disabled,
      ]}
      onPress={onPress}
      disabled={isLoading || disabled}
      activeOpacity={0.7}
    >
      <View style={styles.inner}>
        {isLoading ? (
          <ActivityIndicator color="#ffffff" size="large" />
        ) : (
          <>
            <Text style={styles.label}>
              {isClockedIn ? 'CLOCK OUT' : 'CLOCK IN'}
            </Text>
            <Text style={styles.sublabel}>
              {isClockedIn ? 'Tap to end shift' : 'Tap to start shift'}
            </Text>
          </>
        )}
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  button: {
    width: 200,
    height: 200,
    borderRadius: 100,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
  },
  clockIn: {
    backgroundColor: '#16a34a',
  },
  clockOut: {
    backgroundColor: '#dc2626',
  },
  disabled: {
    opacity: 0.6,
  },
  inner: {
    alignItems: 'center',
  },
  label: {
    color: '#ffffff',
    fontSize: 22,
    fontWeight: '800',
    letterSpacing: 1,
  },
  sublabel: {
    color: 'rgba(255,255,255,0.8)',
    fontSize: 12,
    marginTop: 4,
  },
});
```

- [ ] **Step 4: Create the StatusCard component**

```tsx
// mobile/src/components/StatusCard.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface StatusCardProps {
  title: string;
  value: string;
  subtitle?: string;
  color?: string;
}

export function StatusCard({ title, value, subtitle, color = '#1a56db' }: StatusCardProps) {
  return (
    <View style={styles.card}>
      <Text style={styles.title}>{title}</Text>
      <Text style={[styles.value, { color }]}>{value}</Text>
      {subtitle && <Text style={styles.subtitle}>{subtitle}</Text>}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  title: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  value: {
    fontSize: 28,
    fontWeight: '700',
    marginTop: 4,
  },
  subtitle: {
    fontSize: 13,
    color: '#9ca3af',
    marginTop: 2,
  },
});
```

- [ ] **Step 5: Build the ClockScreen**

```tsx
// mobile/src/screens/clock/ClockScreen.tsx
import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { ClockButton } from '@/components/ClockButton';
import { StatusCard } from '@/components/StatusCard';
import { useClock } from '@/hooks/useClock';
import { formatTime, formatDuration } from '@/utils/time';

export function ClockScreen() {
  const {
    isClockedIn,
    activeEntry,
    activeBreak,
    isLoading,
    error,
    manualClockIn,
    manualClockOut,
    clearError,
  } = useClock();

  const [elapsed, setElapsed] = useState(0);

  // Update elapsed time every second when clocked in
  useEffect(() => {
    if (!isClockedIn || !activeEntry) {
      setElapsed(0);
      return;
    }

    const updateElapsed = () => {
      setElapsed((Date.now() - activeEntry.clockIn) / (1000 * 60 * 60));
    };

    updateElapsed();
    const interval = setInterval(updateElapsed, 1000);
    return () => clearInterval(interval);
  }, [isClockedIn, activeEntry]);

  // Show errors
  useEffect(() => {
    if (error) {
      Alert.alert('Error', error, [{ text: 'OK', onPress: clearError }]);
    }
  }, [error]);

  const handleClockPress = () => {
    if (isClockedIn) {
      Alert.alert(
        'Clock Out',
        'Are you sure you want to clock out?',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Clock Out', style: 'destructive', onPress: manualClockOut },
        ],
      );
    } else {
      manualClockIn();
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Status indicator */}
      <View style={[styles.statusBadge, isClockedIn ? styles.statusActive : styles.statusInactive]}>
        <View style={[styles.statusDot, isClockedIn ? styles.dotActive : styles.dotInactive]} />
        <Text style={[styles.statusText, isClockedIn ? styles.textActive : styles.textInactive]}>
          {isClockedIn ? 'Clocked In' : 'Clocked Out'}
        </Text>
      </View>

      {/* Clock button */}
      <View style={styles.buttonContainer}>
        <ClockButton
          isClockedIn={isClockedIn}
          isLoading={isLoading}
          onPress={handleClockPress}
          disabled={!!activeBreak}
        />
        {activeBreak && (
          <Text style={styles.breakWarning}>End your break before clocking out</Text>
        )}
      </View>

      {/* Info cards */}
      <View style={styles.cards}>
        {isClockedIn && activeEntry && (
          <>
            <StatusCard
              title="Clock In Time"
              value={formatTime(activeEntry.clockIn)}
              subtitle="Today"
            />
            <StatusCard
              title="Time Elapsed"
              value={formatDuration(elapsed)}
              color={elapsed > 8 ? '#dc2626' : '#16a34a'}
              subtitle={elapsed > 8 ? 'Overtime' : 'Regular hours'}
            />
          </>
        )}

        {!isClockedIn && (
          <StatusCard
            title="Status"
            value="Off Duty"
            subtitle="Tap the button above to start your shift"
            color="#6b7280"
          />
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    alignItems: 'center',
    paddingTop: 32,
    paddingHorizontal: 20,
    paddingBottom: 40,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    marginBottom: 40,
  },
  statusActive: {
    backgroundColor: '#dcfce7',
  },
  statusInactive: {
    backgroundColor: '#f3f4f6',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 8,
  },
  dotActive: {
    backgroundColor: '#16a34a',
  },
  dotInactive: {
    backgroundColor: '#9ca3af',
  },
  statusText: {
    fontSize: 14,
    fontWeight: '600',
  },
  textActive: {
    color: '#16a34a',
  },
  textInactive: {
    color: '#6b7280',
  },
  buttonContainer: {
    alignItems: 'center',
    marginBottom: 40,
  },
  breakWarning: {
    marginTop: 12,
    color: '#f59e0b',
    fontSize: 13,
    fontWeight: '500',
  },
  cards: {
    width: '100%',
  },
});
```

- [ ] **Step 6: Write ClockButton component test**

```tsx
// mobile/__tests__/components/ClockButton.test.tsx
import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { ClockButton } from '../../src/components/ClockButton';

describe('ClockButton', () => {
  it('should render CLOCK IN when not clocked in', () => {
    const { getByText } = render(
      <ClockButton isClockedIn={false} isLoading={false} onPress={() => {}} />,
    );
    expect(getByText('CLOCK IN')).toBeTruthy();
    expect(getByText('Tap to start shift')).toBeTruthy();
  });

  it('should render CLOCK OUT when clocked in', () => {
    const { getByText } = render(
      <ClockButton isClockedIn={true} isLoading={false} onPress={() => {}} />,
    );
    expect(getByText('CLOCK OUT')).toBeTruthy();
    expect(getByText('Tap to end shift')).toBeTruthy();
  });

  it('should call onPress when tapped', () => {
    const onPress = jest.fn();
    const { getByText } = render(
      <ClockButton isClockedIn={false} isLoading={false} onPress={onPress} />,
    );
    fireEvent.press(getByText('CLOCK IN'));
    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('should show loading indicator when loading', () => {
    const { queryByText } = render(
      <ClockButton isClockedIn={false} isLoading={true} onPress={() => {}} />,
    );
    expect(queryByText('CLOCK IN')).toBeNull();
  });

  it('should not fire onPress when disabled', () => {
    const onPress = jest.fn();
    const { getByText } = render(
      <ClockButton isClockedIn={false} isLoading={false} onPress={onPress} disabled={true} />,
    );
    fireEvent.press(getByText('CLOCK IN'));
    expect(onPress).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 7: Run tests**

```bash
cd mobile
npx jest __tests__/utils/time.test.ts __tests__/components/ClockButton.test.tsx
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add mobile/src/screens/clock/ mobile/src/components/ mobile/src/utils/time.ts mobile/__tests__/
git commit -m "feat(mobile): add clock in/out UI with GPS capture and elapsed time display"
```

---

## Task 8: Sync Manager

**Files:**
- Create: `mobile/src/services/sync.ts`
- Create: `mobile/src/store/syncStore.ts`
- Create: `mobile/src/hooks/useSync.ts`
- Create: `mobile/src/hooks/useNetworkStatus.ts`
- Create: `mobile/src/components/SyncIndicator.tsx`
- Create: `mobile/__tests__/services/sync.test.ts`

- [ ] **Step 1: Create the network status hook**

```ts
// mobile/src/hooks/useNetworkStatus.ts
import { useEffect, useState } from 'react';
import NetInfo, { NetInfoState } from '@react-native-community/netinfo';

export function useNetworkStatus() {
  const [isConnected, setIsConnected] = useState<boolean>(true);
  const [connectionType, setConnectionType] = useState<string>('unknown');

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state: NetInfoState) => {
      setIsConnected(state.isConnected ?? false);
      setConnectionType(state.type);
    });

    return () => unsubscribe();
  }, []);

  return { isConnected, connectionType };
}

/**
 * Non-hook version for use in services.
 */
export async function checkConnectivity(): Promise<boolean> {
  const state = await NetInfo.fetch();
  return state.isConnected ?? false;
}
```

- [ ] **Step 2: Create the sync service**

```ts
// mobile/src/services/sync.ts
import { Q } from '@nozbe/watermelondb';
import {
  database,
  timeEntriesCollection,
  breaksCollection,
  geofencesCollection,
  teamsCollection,
  jobsCollection,
} from '@/database';
import { apiClient } from './api';
import { checkConnectivity } from '@/hooks/useNetworkStatus';
import { geofenceService } from './geofence';
import type { SyncResponse, TimeEntryData, BreakData } from '@/types';
import * as SecureStore from 'expo-secure-store';

const LAST_SYNCED_KEY = 'geotime_last_synced_at';

class SyncManager {
  private isSyncing = false;

  /**
   * Perform a full sync cycle: push pending local changes, then pull server updates.
   * Returns true if sync succeeded, false otherwise.
   */
  async sync(): Promise<boolean> {
    if (this.isSyncing) {
      console.log('[Sync] Already syncing — skipping.');
      return false;
    }

    const isOnline = await checkConnectivity();
    if (!isOnline) {
      console.log('[Sync] Offline — skipping sync.');
      return false;
    }

    this.isSyncing = true;

    try {
      // 1. Gather pending local data
      const pendingEntries = await this.getPendingTimeEntries();
      const pendingBreaks = await this.getPendingBreaks();

      // 2. Get last sync timestamp
      const lastSyncedAt = await SecureStore.getItemAsync(LAST_SYNCED_KEY);

      // 3. Push local changes and pull server updates in one request
      const response = await apiClient.post<SyncResponse>('/sync', {
        time_entries: pendingEntries.map(this.serializeTimeEntry),
        breaks: pendingBreaks.map(this.serializeBreak),
        last_synced_at: lastSyncedAt ?? null,
      });

      const syncData = response.data.data;

      // 4. Process server response
      await database.write(async () => {
        // Mark confirmed entries as synced
        for (const entryId of syncData.confirmed_entries) {
          const entry = pendingEntries.find(
            (e) => e.id === entryId || e.serverId === entryId,
          );
          if (entry) {
            await entry.update((e) => {
              e.syncStatus = 'synced';
            });
          }
        }

        // Mark confirmed breaks as synced
        for (const breakId of syncData.confirmed_breaks) {
          const brk = pendingBreaks.find(
            (b) => b.id === breakId || b.serverId === breakId,
          );
          if (brk) {
            await brk.update((b) => {
              b.syncStatus = 'synced';
            });
          }
        }

        // Mark conflicts
        for (const conflict of syncData.conflicts) {
          if (conflict.type === 'time_entry') {
            const entry = pendingEntries.find((e) => e.id === conflict.local_id);
            if (entry) {
              await entry.update((e) => {
                e.syncStatus = 'conflict';
                e.serverId = conflict.server_id;
              });
            }
          } else if (conflict.type === 'break') {
            const brk = pendingBreaks.find((b) => b.id === conflict.local_id);
            if (brk) {
              await brk.update((b) => {
                b.syncStatus = 'conflict';
                b.serverId = conflict.server_id;
              });
            }
          }
        }

        // Upsert geofences from server
        await this.upsertGeofences(syncData.geofences);

        // Upsert teams from server
        await this.upsertTeams(syncData.teams);

        // Upsert jobs from server
        await this.upsertJobs(syncData.jobs);
      });

      // 5. Update last synced timestamp
      await SecureStore.setItemAsync(LAST_SYNCED_KEY, syncData.server_time);

      // 6. Re-register geofences with the OS if any were updated
      if (syncData.geofences.length > 0) {
        await geofenceService.registerGeofences();
      }

      console.log(
        `[Sync] Complete. Confirmed ${syncData.confirmed_entries.length} entries, ` +
        `${syncData.confirmed_breaks.length} breaks. ` +
        `Received ${syncData.geofences.length} geofences, ` +
        `${syncData.teams.length} teams, ${syncData.jobs.length} jobs.`,
      );

      this.isSyncing = false;
      return true;
    } catch (err: any) {
      console.error('[Sync] Failed:', err.message);
      this.isSyncing = false;
      return false;
    }
  }

  /**
   * Get all time entries with sync_status = 'pending'.
   */
  private async getPendingTimeEntries() {
    return timeEntriesCollection
      .query(Q.where('sync_status', 'pending'))
      .fetch();
  }

  /**
   * Get all breaks with sync_status = 'pending'.
   */
  private async getPendingBreaks() {
    return breaksCollection
      .query(Q.where('sync_status', 'pending'))
      .fetch();
  }

  /**
   * Serialize a WatermelonDB TimeEntry to API format.
   */
  private serializeTimeEntry(entry: any): TimeEntryData {
    return {
      id: entry.id,
      employee_id: entry.employeeId,
      job_id: entry.jobId,
      team_id: entry.teamId,
      clock_in: new Date(entry.clockIn).toISOString(),
      clock_out: entry.clockOut ? new Date(entry.clockOut).toISOString() : null,
      clock_in_lat: entry.clockInLat,
      clock_in_lng: entry.clockInLng,
      clock_out_lat: entry.clockOutLat,
      clock_out_lng: entry.clockOutLng,
      clock_method: entry.clockMethod,
      total_hours: entry.totalHours,
      status: entry.status,
      sync_status: entry.syncStatus,
      notes: entry.notes,
    };
  }

  /**
   * Serialize a WatermelonDB Break to API format.
   */
  private serializeBreak(brk: any): BreakData {
    return {
      id: brk.id,
      time_entry_id: brk.timeEntryId,
      type: brk.type,
      start_time: new Date(brk.startTime).toISOString(),
      end_time: brk.endTime ? new Date(brk.endTime).toISOString() : null,
      duration_minutes: brk.durationMinutes,
      was_interrupted: brk.wasInterrupted,
      sync_status: brk.syncStatus,
    };
  }

  /**
   * Upsert geofences from server data into WatermelonDB.
   */
  private async upsertGeofences(geofences: any[]): Promise<void> {
    for (const gf of geofences) {
      const existing = await geofencesCollection
        .query(Q.where('server_id', gf.id))
        .fetch();

      if (existing.length > 0) {
        await existing[0].update((g) => {
          g.name = gf.name;
          g.latitude = gf.latitude;
          g.longitude = gf.longitude;
          g.radiusMeters = gf.radius_meters;
          g.isActive = gf.is_active;
        });
      } else {
        await geofencesCollection.create((g) => {
          g.serverId = gf.id;
          g.jobId = gf.job_id;
          g.name = gf.name;
          g.latitude = gf.latitude;
          g.longitude = gf.longitude;
          g.radiusMeters = gf.radius_meters;
          g.isActive = gf.is_active;
        });
      }
    }
  }

  /**
   * Upsert teams from server data into WatermelonDB.
   */
  private async upsertTeams(teams: any[]): Promise<void> {
    for (const team of teams) {
      const existing = await teamsCollection
        .query(Q.where('server_id', team.id))
        .fetch();

      if (existing.length > 0) {
        await existing[0].update((t) => {
          t.name = team.name;
          t.colorTag = team.color_tag;
          t.status = team.status;
        });
      } else {
        await teamsCollection.create((t) => {
          t.serverId = team.id;
          t.name = team.name;
          t.colorTag = team.color_tag;
          t.status = team.status;
        });
      }
    }
  }

  /**
   * Upsert jobs from server data into WatermelonDB.
   */
  private async upsertJobs(jobs: any[]): Promise<void> {
    for (const job of jobs) {
      const existing = await jobsCollection
        .query(Q.where('server_id', job.id))
        .fetch();

      if (existing.length > 0) {
        await existing[0].update((j) => {
          j.name = job.name;
          j.clientName = job.client_name;
          j.address = job.address;
          j.status = job.status;
        });
      } else {
        await jobsCollection.create((j) => {
          j.serverId = job.id;
          j.name = job.name;
          j.clientName = job.client_name;
          j.address = job.address ?? '';
          j.status = job.status;
        });
      }
    }
  }

  /**
   * Get the last sync timestamp.
   */
  async getLastSyncedAt(): Promise<string | null> {
    return SecureStore.getItemAsync(LAST_SYNCED_KEY);
  }

  /**
   * Get count of pending (unsynced) entries.
   */
  async getPendingCount(): Promise<number> {
    const entries = await timeEntriesCollection
      .query(Q.where('sync_status', 'pending'))
      .fetchCount();
    const breaks = await breaksCollection
      .query(Q.where('sync_status', 'pending'))
      .fetchCount();
    return entries + breaks;
  }

  /**
   * Clear sync data (on logout).
   */
  async clearSyncData(): Promise<void> {
    await SecureStore.deleteItemAsync(LAST_SYNCED_KEY);
  }
}

export const syncManager = new SyncManager();
```

- [ ] **Step 3: Create the sync store**

```ts
// mobile/src/store/syncStore.ts
import { create } from 'zustand';
import { syncManager } from '@/services/sync';
import { checkConnectivity } from '@/hooks/useNetworkStatus';

interface SyncState {
  isSyncing: boolean;
  lastSyncedAt: string | null;
  pendingCount: number;
  isOnline: boolean;
  syncError: string | null;

  triggerSync: () => Promise<void>;
  refreshStatus: () => Promise<void>;
  setOnline: (online: boolean) => void;
}

export const useSyncStore = create<SyncState>((set, get) => ({
  isSyncing: false,
  lastSyncedAt: null,
  pendingCount: 0,
  isOnline: true,
  syncError: null,

  triggerSync: async () => {
    if (get().isSyncing) return;

    set({ isSyncing: true, syncError: null });

    try {
      const success = await syncManager.sync();
      if (success) {
        const lastSyncedAt = await syncManager.getLastSyncedAt();
        const pendingCount = await syncManager.getPendingCount();
        set({ lastSyncedAt, pendingCount, isSyncing: false });
      } else {
        set({ isSyncing: false });
      }
    } catch (err: any) {
      set({
        isSyncing: false,
        syncError: err.message ?? 'Sync failed',
      });
    }
  },

  refreshStatus: async () => {
    const lastSyncedAt = await syncManager.getLastSyncedAt();
    const pendingCount = await syncManager.getPendingCount();
    const isOnline = await checkConnectivity();
    set({ lastSyncedAt, pendingCount, isOnline });
  },

  setOnline: (online: boolean) => set({ isOnline: online }),
}));
```

- [ ] **Step 4: Create the useSync hook**

```ts
// mobile/src/hooks/useSync.ts
import { useEffect, useRef } from 'react';
import { AppState, AppStateStatus } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { useSyncStore } from '@/store/syncStore';

const SYNC_INTERVAL_MS = 5 * 60 * 1000; // 5 minutes

export function useSync() {
  const { triggerSync, refreshStatus, setOnline, ...state } = useSyncStore();
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    // Initial status refresh
    refreshStatus();

    // Sync on connectivity change
    const unsubscribeNetInfo = NetInfo.addEventListener((netState) => {
      const online = netState.isConnected ?? false;
      setOnline(online);

      // Sync immediately when coming back online
      if (online) {
        triggerSync();
      }
    });

    // Sync when app comes to foreground
    const handleAppStateChange = (nextState: AppStateStatus) => {
      if (nextState === 'active') {
        triggerSync();
      }
    };
    const appStateSub = AppState.addEventListener('change', handleAppStateChange);

    // Periodic sync
    intervalRef.current = setInterval(() => {
      triggerSync();
    }, SYNC_INTERVAL_MS);

    // Initial sync
    triggerSync();

    return () => {
      unsubscribeNetInfo();
      appStateSub.remove();
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, []);

  return {
    ...state,
    triggerSync,
    refreshStatus,
  };
}
```

- [ ] **Step 5: Create the SyncIndicator component**

```tsx
// mobile/src/components/SyncIndicator.tsx
import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useSyncStore } from '@/store/syncStore';

export function SyncIndicator() {
  const { isSyncing, lastSyncedAt, pendingCount, isOnline, triggerSync } = useSyncStore();

  const getStatusColor = (): string => {
    if (!isOnline) return '#f59e0b'; // yellow — offline
    if (pendingCount > 0) return '#f59e0b'; // yellow — pending
    return '#16a34a'; // green — synced
  };

  const getStatusText = (): string => {
    if (!isOnline) return 'Offline';
    if (isSyncing) return 'Syncing...';
    if (pendingCount > 0) return `${pendingCount} pending`;
    if (lastSyncedAt) {
      const ago = getTimeAgo(lastSyncedAt);
      return `Synced ${ago}`;
    }
    return 'Not synced';
  };

  return (
    <TouchableOpacity
      style={styles.container}
      onPress={triggerSync}
      disabled={isSyncing || !isOnline}
      activeOpacity={0.7}
    >
      {isSyncing ? (
        <ActivityIndicator size="small" color="#1a56db" />
      ) : (
        <View style={[styles.dot, { backgroundColor: getStatusColor() }]} />
      )}
      <Text style={styles.text}>{getStatusText()}</Text>
    </TouchableOpacity>
  );
}

function getTimeAgo(isoString: string): string {
  const then = new Date(isoString).getTime();
  const now = Date.now();
  const diffMin = Math.floor((now - then) / (1000 * 60));

  if (diffMin < 1) return 'just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${Math.floor(diffHours / 24)}d ago`;
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#f9fafb',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  text: {
    fontSize: 12,
    fontWeight: '500',
    color: '#6b7280',
  },
});
```

- [ ] **Step 6: Write sync service tests**

```ts
// mobile/__tests__/services/sync.test.ts
import { syncManager } from '../../src/services/sync';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('../../src/database', () => ({
  database: {
    write: jest.fn((fn) => fn()),
  },
  timeEntriesCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
      fetchCount: jest.fn().mockResolvedValue(0),
    })),
    create: jest.fn(),
  },
  breaksCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
      fetchCount: jest.fn().mockResolvedValue(0),
    })),
    create: jest.fn(),
  },
  geofencesCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
    })),
    create: jest.fn(),
  },
  teamsCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
    })),
    create: jest.fn(),
  },
  jobsCollection: {
    query: jest.fn(() => ({
      fetch: jest.fn().mockResolvedValue([]),
    })),
    create: jest.fn(),
  },
}));

jest.mock('../../src/services/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
  TOKEN_KEY: 'geotime_auth_token',
}));

jest.mock('../../src/services/geofence', () => ({
  geofenceService: {
    registerGeofences: jest.fn().mockResolvedValue(undefined),
  },
}));

jest.mock('../../src/hooks/useNetworkStatus', () => ({
  checkConnectivity: jest.fn().mockResolvedValue(true),
}));

import { apiClient } from '../../src/services/api';
import { checkConnectivity } from '../../src/hooks/useNetworkStatus';

describe('SyncManager', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('sync', () => {
    it('should skip sync when offline', async () => {
      (checkConnectivity as jest.Mock).mockResolvedValue(false);

      const result = await syncManager.sync();

      expect(result).toBe(false);
      expect(apiClient.post).not.toHaveBeenCalled();
    });

    it('should call sync endpoint when online', async () => {
      (checkConnectivity as jest.Mock).mockResolvedValue(true);
      (apiClient.post as jest.Mock).mockResolvedValue({
        data: {
          data: {
            confirmed_entries: [],
            confirmed_breaks: [],
            conflicts: [],
            geofences: [],
            teams: [],
            jobs: [],
            server_time: '2026-03-28T12:00:00Z',
          },
        },
      });

      const result = await syncManager.sync();

      expect(result).toBe(true);
      expect(apiClient.post).toHaveBeenCalledWith('/sync', expect.any(Object));
    });
  });

  describe('getPendingCount', () => {
    it('should return combined count of pending entries and breaks', async () => {
      const count = await syncManager.getPendingCount();
      expect(typeof count).toBe('number');
    });
  });

  describe('getLastSyncedAt', () => {
    it('should return null when never synced', async () => {
      const result = await syncManager.getLastSyncedAt();
      expect(result).toBeNull();
    });
  });
});
```

- [ ] **Step 7: Run tests**

```bash
cd mobile
npx jest __tests__/services/sync.test.ts
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add mobile/src/services/sync.ts mobile/src/store/syncStore.ts mobile/src/hooks/useSync.ts mobile/src/hooks/useNetworkStatus.ts mobile/src/components/SyncIndicator.tsx mobile/__tests__/services/sync.test.ts
git commit -m "feat(mobile): add sync manager with bulk push/pull, conflict handling, and connectivity detection"
```

---

## Task 9: Employee Dashboard

**Files:**
- Modify: `mobile/src/screens/dashboard/DashboardScreen.tsx`
- Create: `mobile/src/components/WeeklyHoursBar.tsx`
- Create: `mobile/__tests__/components/StatusCard.test.tsx`

- [ ] **Step 1: Create the WeeklyHoursBar component**

```tsx
// mobile/src/components/WeeklyHoursBar.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface WeeklyHoursBarProps {
  currentHours: number;
  targetHours: number;
  overtimeThreshold: number;
}

export function WeeklyHoursBar({
  currentHours,
  targetHours,
  overtimeThreshold,
}: WeeklyHoursBarProps) {
  const percentage = Math.min((currentHours / targetHours) * 100, 100);
  const isOvertime = currentHours > overtimeThreshold;
  const overtimeHours = isOvertime ? currentHours - overtimeThreshold : 0;

  const getBarColor = (): string => {
    if (isOvertime) return '#dc2626';
    if (percentage >= 90) return '#f59e0b';
    return '#1a56db';
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.label}>Weekly Hours</Text>
        <Text style={styles.value}>
          {currentHours.toFixed(1)} / {targetHours}h
        </Text>
      </View>

      <View style={styles.barBackground}>
        <View
          style={[
            styles.barFill,
            { width: `${percentage}%`, backgroundColor: getBarColor() },
          ]}
        />
        {/* Overtime threshold marker */}
        <View
          style={[
            styles.marker,
            { left: `${(overtimeThreshold / targetHours) * 100}%` },
          ]}
        />
      </View>

      <View style={styles.footer}>
        {isOvertime ? (
          <Text style={styles.overtime}>
            {overtimeHours.toFixed(1)}h overtime (1.5x)
          </Text>
        ) : (
          <Text style={styles.remaining}>
            {(targetHours - currentHours).toFixed(1)}h remaining
          </Text>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  value: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  barBackground: {
    height: 12,
    backgroundColor: '#e5e7eb',
    borderRadius: 6,
    overflow: 'hidden',
    position: 'relative',
  },
  barFill: {
    height: '100%',
    borderRadius: 6,
  },
  marker: {
    position: 'absolute',
    top: 0,
    width: 2,
    height: '100%',
    backgroundColor: '#374151',
  },
  footer: {
    marginTop: 6,
  },
  overtime: {
    fontSize: 12,
    fontWeight: '600',
    color: '#dc2626',
  },
  remaining: {
    fontSize: 12,
    color: '#6b7280',
  },
});
```

- [ ] **Step 2: Build the DashboardScreen**

```tsx
// mobile/src/screens/dashboard/DashboardScreen.tsx
import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, RefreshControl } from 'react-native';
import { Q } from '@nozbe/watermelondb';
import { useAuth } from '@/hooks/useAuth';
import { useClock } from '@/hooks/useClock';
import { useSync } from '@/hooks/useSync';
import { StatusCard } from '@/components/StatusCard';
import { WeeklyHoursBar } from '@/components/WeeklyHoursBar';
import { SyncIndicator } from '@/components/SyncIndicator';
import { timeEntriesCollection } from '@/database';
import { formatTime, formatDuration, startOfToday, startOfWeek, calculateTotalHours } from '@/utils/time';

export function DashboardScreen() {
  const { user, tenant } = useAuth();
  const { isClockedIn, activeEntry, activeBreak } = useClock();
  const { triggerSync, isSyncing } = useSync();

  const [todayHours, setTodayHours] = useState(0);
  const [weeklyHours, setWeeklyHours] = useState(0);
  const [refreshing, setRefreshing] = useState(false);

  const loadHours = useCallback(async () => {
    if (!user) return;

    try {
      // Today's entries
      const todayStart = startOfToday();
      const todayEntries = await timeEntriesCollection
        .query(
          Q.where('employee_id', user.id),
          Q.where('clock_in', Q.gte(todayStart)),
        )
        .fetch();

      const today = calculateTotalHours(
        todayEntries.map((e) => ({ clockIn: e.clockIn, clockOut: e.clockOut })),
      );
      setTodayHours(today);

      // Weekly entries
      const weekStart = startOfWeek();
      const weekEntries = await timeEntriesCollection
        .query(
          Q.where('employee_id', user.id),
          Q.where('clock_in', Q.gte(weekStart)),
        )
        .fetch();

      const weekly = calculateTotalHours(
        weekEntries.map((e) => ({ clockIn: e.clockIn, clockOut: e.clockOut })),
      );
      setWeeklyHours(weekly);
    } catch (err) {
      console.error('[Dashboard] Error loading hours:', err);
    }
  }, [user]);

  useEffect(() => {
    loadHours();
    // Refresh every 60 seconds
    const interval = setInterval(loadHours, 60000);
    return () => clearInterval(interval);
  }, [loadHours]);

  const onRefresh = async () => {
    setRefreshing(true);
    await triggerSync();
    await loadHours();
    setRefreshing(false);
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#1a56db" />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>
            Hello, {user?.name?.split(' ')[0] ?? 'there'}
          </Text>
          <Text style={styles.company}>{tenant?.name}</Text>
        </View>
        <SyncIndicator />
      </View>

      {/* Current status */}
      <View style={[styles.statusBar, isClockedIn ? styles.statusBarActive : styles.statusBarInactive]}>
        <View style={[styles.statusDot, isClockedIn ? styles.dotGreen : styles.dotGray]} />
        <Text style={styles.statusLabel}>
          {isClockedIn
            ? `Clocked in since ${activeEntry ? formatTime(activeEntry.clockIn) : '--'}`
            : 'Currently off duty'}
        </Text>
        {activeBreak && (
          <Text style={styles.breakLabel}> (On break)</Text>
        )}
      </View>

      {/* Today's hours */}
      <StatusCard
        title="Today"
        value={formatDuration(todayHours)}
        subtitle={isClockedIn ? 'In progress' : 'Completed'}
        color={todayHours > 8 ? '#dc2626' : '#1a56db'}
      />

      {/* Weekly progress */}
      <WeeklyHoursBar
        currentHours={weeklyHours}
        targetHours={40}
        overtimeThreshold={40}
      />

      {/* Break status */}
      {isClockedIn && (
        <StatusCard
          title="Break Status"
          value={activeBreak ? 'On Break' : 'Working'}
          subtitle={activeBreak
            ? `${activeBreak.type === 'PAID_REST' ? 'Paid rest' : 'Meal break'} in progress`
            : 'No active break'}
          color={activeBreak ? '#f59e0b' : '#16a34a'}
        />
      )}

      {/* Quick info */}
      <View style={styles.infoRow}>
        <View style={styles.infoItem}>
          <Text style={styles.infoLabel}>Role</Text>
          <Text style={styles.infoValue}>{user?.role?.replace('_', ' ') ?? '--'}</Text>
        </View>
        <View style={styles.infoItem}>
          <Text style={styles.infoLabel}>Week Progress</Text>
          <Text style={styles.infoValue}>{Math.round((weeklyHours / 40) * 100)}%</Text>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  greeting: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  company: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  statusBar: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
  },
  statusBarActive: {
    backgroundColor: '#dcfce7',
  },
  statusBarInactive: {
    backgroundColor: '#f3f4f6',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 8,
  },
  dotGreen: {
    backgroundColor: '#16a34a',
  },
  dotGray: {
    backgroundColor: '#9ca3af',
  },
  statusLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#374151',
  },
  breakLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#f59e0b',
  },
  infoRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 4,
  },
  infoItem: {
    flex: 1,
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
  },
  infoValue: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginTop: 4,
    textTransform: 'capitalize',
  },
});
```

- [ ] **Step 3: Write StatusCard component test**

```tsx
// mobile/__tests__/components/StatusCard.test.tsx
import React from 'react';
import { render } from '@testing-library/react-native';
import { StatusCard } from '../../src/components/StatusCard';

describe('StatusCard', () => {
  it('should render title and value', () => {
    const { getByText } = render(
      <StatusCard title="Today" value="8h 30m" />,
    );
    expect(getByText('Today')).toBeTruthy();
    expect(getByText('8h 30m')).toBeTruthy();
  });

  it('should render subtitle when provided', () => {
    const { getByText } = render(
      <StatusCard title="Status" value="Active" subtitle="Since 8:00 AM" />,
    );
    expect(getByText('Since 8:00 AM')).toBeTruthy();
  });

  it('should not render subtitle when not provided', () => {
    const { queryByText } = render(
      <StatusCard title="Status" value="Active" />,
    );
    // No subtitle element
    expect(queryByText('Since')).toBeNull();
  });
});
```

- [ ] **Step 4: Run tests**

```bash
cd mobile
npx jest __tests__/components/StatusCard.test.tsx
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add mobile/src/screens/dashboard/ mobile/src/components/WeeklyHoursBar.tsx mobile/__tests__/components/StatusCard.test.tsx
git commit -m "feat(mobile): add employee dashboard with today's hours, weekly progress, and sync indicator"
```

---

## Task 10: Timesheet View

**Files:**
- Modify: `mobile/src/screens/timesheet/TimesheetScreen.tsx`
- Create: `mobile/src/components/TimesheetRow.tsx`

- [ ] **Step 1: Create the TimesheetRow component**

```tsx
// mobile/src/components/TimesheetRow.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { formatTime, formatDuration } from '@/utils/time';

interface TimesheetRowProps {
  date: string; // "Mon, Jan 1"
  clockIn: number;
  clockOut: number | null;
  totalHours: number | null;
  method: string;
  status: string;
  syncStatus: string;
}

export function TimesheetRow({
  date,
  clockIn,
  clockOut,
  totalHours,
  method,
  status,
  syncStatus,
}: TimesheetRowProps) {
  const getStatusColor = (): string => {
    switch (status) {
      case 'APPROVED': return '#16a34a';
      case 'REJECTED': return '#dc2626';
      case 'SUBMITTED': return '#f59e0b';
      case 'ACTIVE': return '#1a56db';
      default: return '#6b7280';
    }
  };

  const getSyncIcon = (): string => {
    switch (syncStatus) {
      case 'synced': return 'S';
      case 'pending': return 'P';
      case 'conflict': return '!';
      default: return '?';
    }
  };

  const getSyncColor = (): string => {
    switch (syncStatus) {
      case 'synced': return '#16a34a';
      case 'pending': return '#f59e0b';
      case 'conflict': return '#dc2626';
      default: return '#6b7280';
    }
  };

  return (
    <View style={styles.row}>
      <View style={styles.dateCol}>
        <Text style={styles.date}>{date}</Text>
        <View style={[styles.methodBadge]}>
          <Text style={styles.methodText}>{method}</Text>
        </View>
      </View>

      <View style={styles.timeCol}>
        <Text style={styles.timeIn}>{formatTime(clockIn)}</Text>
        <Text style={styles.timeSep}>to</Text>
        <Text style={styles.timeOut}>
          {clockOut ? formatTime(clockOut) : 'Active'}
        </Text>
      </View>

      <View style={styles.hoursCol}>
        <Text style={styles.hours}>
          {totalHours ? formatDuration(totalHours) : '--'}
        </Text>
        <View style={styles.statusRow}>
          <View style={[styles.statusDot, { backgroundColor: getStatusColor() }]} />
          <Text style={[styles.syncBadge, { color: getSyncColor() }]}>
            {getSyncIcon()}
          </Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  dateCol: {
    flex: 2,
  },
  date: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  methodBadge: {
    marginTop: 2,
  },
  methodText: {
    fontSize: 10,
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  timeCol: {
    flex: 3,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  timeIn: {
    fontSize: 13,
    color: '#374151',
    fontWeight: '500',
  },
  timeSep: {
    fontSize: 11,
    color: '#9ca3af',
  },
  timeOut: {
    fontSize: 13,
    color: '#374151',
    fontWeight: '500',
  },
  hoursCol: {
    flex: 1.5,
    alignItems: 'flex-end',
  },
  hours: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 2,
    gap: 4,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  syncBadge: {
    fontSize: 10,
    fontWeight: '700',
  },
});
```

- [ ] **Step 2: Build the TimesheetScreen**

```tsx
// mobile/src/screens/timesheet/TimesheetScreen.tsx
import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { Q } from '@nozbe/watermelondb';
import { useAuth } from '@/hooks/useAuth';
import { timeEntriesCollection } from '@/database';
import { TimesheetRow } from '@/components/TimesheetRow';
import { formatDateShort, formatDuration, startOfWeek, calculateTotalHours } from '@/utils/time';
import type TimeEntry from '@/database/models/TimeEntry';

export function TimesheetScreen() {
  const { user } = useAuth();
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [weekOffset, setWeekOffset] = useState(0); // 0 = current week, -1 = last week, etc.
  const [weekTotal, setWeekTotal] = useState(0);
  const [refreshing, setRefreshing] = useState(false);

  const getWeekRange = useCallback(() => {
    const start = new Date();
    start.setDate(start.getDate() + weekOffset * 7);
    const weekStart = startOfWeek();
    const adjustedStart = weekStart + weekOffset * 7 * 24 * 60 * 60 * 1000;
    const adjustedEnd = adjustedStart + 7 * 24 * 60 * 60 * 1000;
    return { start: adjustedStart, end: adjustedEnd };
  }, [weekOffset]);

  const loadEntries = useCallback(async () => {
    if (!user) return;

    const { start, end } = getWeekRange();

    try {
      const results = await timeEntriesCollection
        .query(
          Q.where('employee_id', user.id),
          Q.where('clock_in', Q.gte(start)),
          Q.where('clock_in', Q.lt(end)),
          Q.sortBy('clock_in', Q.desc),
        )
        .fetch();

      setEntries(results);

      const total = calculateTotalHours(
        results.map((e) => ({ clockIn: e.clockIn, clockOut: e.clockOut })),
      );
      setWeekTotal(total);
    } catch (err) {
      console.error('[Timesheet] Error loading entries:', err);
    }
  }, [user, getWeekRange]);

  useEffect(() => {
    loadEntries();
  }, [loadEntries]);

  const onRefresh = async () => {
    setRefreshing(true);
    await loadEntries();
    setRefreshing(false);
  };

  const getWeekLabel = (): string => {
    if (weekOffset === 0) return 'This Week';
    if (weekOffset === -1) return 'Last Week';
    const { start, end } = getWeekRange();
    const startDate = new Date(start);
    const endDate = new Date(end - 1);
    return `${startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
  };

  const renderEntry = ({ item }: { item: TimeEntry }) => (
    <TimesheetRow
      date={formatDateShort(item.clockIn)}
      clockIn={item.clockIn}
      clockOut={item.clockOut}
      totalHours={item.totalHours}
      method={item.clockMethod}
      status={item.status}
      syncStatus={item.syncStatus}
    />
  );

  return (
    <View style={styles.container}>
      {/* Week navigator */}
      <View style={styles.weekNav}>
        <TouchableOpacity
          style={styles.navButton}
          onPress={() => setWeekOffset((o) => o - 1)}
        >
          <Text style={styles.navButtonText}>Previous</Text>
        </TouchableOpacity>

        <View style={styles.weekInfo}>
          <Text style={styles.weekLabel}>{getWeekLabel()}</Text>
          <Text style={styles.weekTotal}>Total: {formatDuration(weekTotal)}</Text>
        </View>

        <TouchableOpacity
          style={[styles.navButton, weekOffset >= 0 && styles.navButtonDisabled]}
          onPress={() => setWeekOffset((o) => Math.min(o + 1, 0))}
          disabled={weekOffset >= 0}
        >
          <Text style={[styles.navButtonText, weekOffset >= 0 && styles.navButtonTextDisabled]}>
            Next
          </Text>
        </TouchableOpacity>
      </View>

      {/* Column headers */}
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, { flex: 2 }]}>Date</Text>
        <Text style={[styles.headerText, { flex: 3 }]}>Time</Text>
        <Text style={[styles.headerText, { flex: 1.5, textAlign: 'right' }]}>Hours</Text>
      </View>

      {/* Entries list */}
      <FlatList
        data={entries}
        keyExtractor={(item) => item.id}
        renderItem={renderEntry}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#1a56db" />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>No entries</Text>
            <Text style={styles.emptySubtitle}>
              No time entries found for this week.
            </Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  weekNav: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  navButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  navButtonDisabled: {
    opacity: 0.3,
  },
  navButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1a56db',
  },
  navButtonTextDisabled: {
    color: '#9ca3af',
  },
  weekInfo: {
    alignItems: 'center',
  },
  weekLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  weekTotal: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  tableHeader: {
    flexDirection: 'row',
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: '#f3f4f6',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  headerText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  emptyState: {
    padding: 40,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 4,
  },
});
```

- [ ] **Step 3: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add mobile/src/screens/timesheet/ mobile/src/components/TimesheetRow.tsx
git commit -m "feat(mobile): add timesheet view with weekly navigation and entry list"
```

---

## Task 11: Break Management

**Files:**
- Modify: `mobile/src/screens/breaks/BreakScreen.tsx`
- Create: `mobile/src/components/BreakTimer.tsx`

- [ ] **Step 1: Create the BreakTimer component**

```tsx
// mobile/src/components/BreakTimer.tsx
import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface BreakTimerProps {
  startTime: number; // timestamp in ms
  type: string; // PAID_REST or UNPAID_MEAL
}

export function BreakTimer({ startTime, type }: BreakTimerProps) {
  const [elapsed, setElapsed] = useState(0);

  useEffect(() => {
    const updateElapsed = () => {
      setElapsed(Math.floor((Date.now() - startTime) / 1000));
    };

    updateElapsed();
    const interval = setInterval(updateElapsed, 1000);
    return () => clearInterval(interval);
  }, [startTime]);

  const minutes = Math.floor(elapsed / 60);
  const seconds = elapsed % 60;

  const isPaidRest = type === 'PAID_REST';
  const maxMinutes = isPaidRest ? 20 : 30;
  const isOverTime = minutes >= maxMinutes;

  return (
    <View style={styles.container}>
      <Text style={styles.label}>
        {isPaidRest ? 'Paid Rest Break' : 'Unpaid Meal Break'}
      </Text>
      <Text style={[styles.timer, isOverTime && styles.timerOvertime]}>
        {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
      </Text>
      {isOverTime && (
        <Text style={styles.warning}>
          {isPaidRest
            ? 'Paid rest breaks should be 20 minutes or less'
            : 'Meal break minimum of 30 minutes met'}
        </Text>
      )}
      {!isOverTime && !isPaidRest && minutes >= 25 && (
        <Text style={styles.info}>
          FLSA: Meal breaks must be at least 30 uninterrupted minutes
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#ffffff',
    borderRadius: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 8,
  },
  timer: {
    fontSize: 56,
    fontWeight: '200',
    color: '#111827',
    fontVariant: ['tabular-nums'],
  },
  timerOvertime: {
    color: '#dc2626',
  },
  warning: {
    fontSize: 12,
    color: '#dc2626',
    marginTop: 8,
    fontWeight: '500',
  },
  info: {
    fontSize: 12,
    color: '#f59e0b',
    marginTop: 8,
    fontWeight: '500',
  },
});
```

- [ ] **Step 2: Build the BreakScreen**

```tsx
// mobile/src/screens/breaks/BreakScreen.tsx
import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Alert,
  FlatList,
} from 'react-native';
import { Q } from '@nozbe/watermelondb';
import { useClock } from '@/hooks/useClock';
import { useAuth } from '@/hooks/useAuth';
import { BreakTimer } from '@/components/BreakTimer';
import { breaksCollection, timeEntriesCollection } from '@/database';
import { formatTime, formatMinutes } from '@/utils/time';
import type BreakModel from '@/database/models/Break';

export function BreakScreen() {
  const { user } = useAuth();
  const { isClockedIn, activeEntry, activeBreak, startBreak, endBreak, isLoading } = useClock();
  const [todayBreaks, setTodayBreaks] = useState<BreakModel[]>([]);

  const loadTodayBreaks = useCallback(async () => {
    if (!activeEntry) {
      setTodayBreaks([]);
      return;
    }

    try {
      const breaks = await breaksCollection
        .query(
          Q.where('time_entry_id', activeEntry.id),
          Q.sortBy('start_time', Q.desc),
        )
        .fetch();
      setTodayBreaks(breaks);
    } catch (err) {
      console.error('[Breaks] Error loading breaks:', err);
    }
  }, [activeEntry]);

  useEffect(() => {
    loadTodayBreaks();
    const interval = setInterval(loadTodayBreaks, 30000);
    return () => clearInterval(interval);
  }, [loadTodayBreaks]);

  const handleStartBreak = (type: 'PAID_REST' | 'UNPAID_MEAL') => {
    Alert.alert(
      type === 'PAID_REST' ? 'Start Paid Rest Break' : 'Start Meal Break',
      type === 'PAID_REST'
        ? 'Starting a paid rest break (up to 20 minutes).'
        : 'Starting an unpaid meal break (minimum 30 minutes for FLSA compliance).',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Start Break',
          onPress: async () => {
            await startBreak(type);
            loadTodayBreaks();
          },
        },
      ],
    );
  };

  const handleEndBreak = () => {
    if (activeBreak && activeBreak.type === 'UNPAID_MEAL') {
      const elapsed = (Date.now() - activeBreak.startTime) / (1000 * 60);
      if (elapsed < 30) {
        Alert.alert(
          'Short Meal Break',
          `This meal break is only ${Math.round(elapsed)} minutes. FLSA requires meal breaks to be at least 30 uninterrupted minutes. If ended early, this break will count as worked time.\n\nEnd break anyway?`,
          [
            { text: 'Keep on Break', style: 'cancel' },
            {
              text: 'End Break',
              style: 'destructive',
              onPress: async () => {
                await endBreak();
                loadTodayBreaks();
              },
            },
          ],
        );
        return;
      }
    }

    endBreak().then(() => loadTodayBreaks());
  };

  if (!isClockedIn) {
    return (
      <View style={styles.emptyContainer}>
        <Text style={styles.emptyTitle}>Not Clocked In</Text>
        <Text style={styles.emptySubtitle}>
          You need to be clocked in to take a break.
        </Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Active break timer */}
      {activeBreak && (
        <>
          <BreakTimer startTime={activeBreak.startTime} type={activeBreak.type} />
          <TouchableOpacity
            style={styles.endBreakButton}
            onPress={handleEndBreak}
            disabled={isLoading}
          >
            <Text style={styles.endBreakText}>End Break</Text>
          </TouchableOpacity>
        </>
      )}

      {/* Start break buttons */}
      {!activeBreak && (
        <View style={styles.breakButtons}>
          <Text style={styles.sectionTitle}>Start a Break</Text>

          <TouchableOpacity
            style={styles.breakOption}
            onPress={() => handleStartBreak('PAID_REST')}
            disabled={isLoading}
          >
            <View>
              <Text style={styles.breakOptionTitle}>Paid Rest Break</Text>
              <Text style={styles.breakOptionDesc}>Up to 20 minutes, counts as work time</Text>
            </View>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.breakOption}
            onPress={() => handleStartBreak('UNPAID_MEAL')}
            disabled={isLoading}
          >
            <View>
              <Text style={styles.breakOptionTitle}>Unpaid Meal Break</Text>
              <Text style={styles.breakOptionDesc}>Minimum 30 minutes (FLSA), unpaid</Text>
            </View>
          </TouchableOpacity>
        </View>
      )}

      {/* Today's break history */}
      <View style={styles.history}>
        <Text style={styles.sectionTitle}>Today's Breaks</Text>
        {todayBreaks.filter((b) => !b.isActive).length === 0 ? (
          <Text style={styles.noBreaks}>No completed breaks today.</Text>
        ) : (
          todayBreaks
            .filter((b) => !b.isActive)
            .map((brk) => (
              <View key={brk.id} style={styles.historyRow}>
                <View>
                  <Text style={styles.historyType}>
                    {brk.type === 'PAID_REST' ? 'Paid Rest' : 'Meal Break'}
                  </Text>
                  <Text style={styles.historyTime}>
                    {formatTime(brk.startTime)} - {brk.endTime ? formatTime(brk.endTime) : 'Active'}
                  </Text>
                </View>
                <Text style={styles.historyDuration}>
                  {brk.durationMinutes ? formatMinutes(brk.durationMinutes) : '--'}
                </Text>
              </View>
            ))
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    padding: 20,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#374151',
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 8,
    textAlign: 'center',
  },
  endBreakButton: {
    backgroundColor: '#dc2626',
    borderRadius: 12,
    paddingVertical: 16,
    alignItems: 'center',
    marginBottom: 24,
  },
  endBreakText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
  },
  breakButtons: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 12,
  },
  breakOption: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  breakOptionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  breakOptionDesc: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  history: {
    marginTop: 8,
  },
  noBreaks: {
    fontSize: 14,
    color: '#9ca3af',
    fontStyle: 'italic',
  },
  historyRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    padding: 12,
    borderRadius: 8,
    marginBottom: 6,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  historyType: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  historyTime: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  historyDuration: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
});
```

- [ ] **Step 3: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 4: Manual verification**

Build and run on device/simulator:
- Clock in first, then navigate to Breaks tab
- Verify "Not Clocked In" message when not clocked in
- Verify break type selection (Paid Rest / Meal Break)
- Verify break timer counts up
- Verify FLSA warning when ending meal break < 30 minutes
- Verify break appears in history after ending

- [ ] **Step 5: Commit**

```bash
git add mobile/src/screens/breaks/ mobile/src/components/BreakTimer.tsx
git commit -m "feat(mobile): add break management with timer, type selection, and FLSA compliance warnings"
```

---

## Task 12: Push Notifications

**Files:**
- Create: `mobile/src/services/notifications.ts`

- [ ] **Step 1: Create the notifications service**

```ts
// mobile/src/services/notifications.ts
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import { Platform } from 'react-native';
import { apiClient } from './api';

// Configure notification behavior when app is in foreground
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

class NotificationService {
  private expoPushToken: string | null = null;

  /**
   * Initialize push notifications and register token with server.
   */
  async initialize(): Promise<void> {
    if (!Device.isDevice) {
      console.log('[Notifications] Push notifications require a physical device.');
      return;
    }

    // Request permission
    const { status: existingStatus } = await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;

    if (existingStatus !== 'granted') {
      const { status } = await Notifications.requestPermissionsAsync();
      finalStatus = status;
    }

    if (finalStatus !== 'granted') {
      console.log('[Notifications] Permission not granted.');
      return;
    }

    // Get push token
    try {
      const tokenData = await Notifications.getExpoPushTokenAsync({
        projectId: 'your-expo-project-id', // Replace with actual project ID from app.json
      });
      this.expoPushToken = tokenData.data;
      console.log('[Notifications] Push token:', this.expoPushToken);

      // Register token with server
      await this.registerTokenWithServer(this.expoPushToken);
    } catch (err) {
      console.error('[Notifications] Error getting push token:', err);
    }

    // Configure Android notification channel
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'GeoTime Notifications',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#1a56db',
      });

      await Notifications.setNotificationChannelAsync('clock', {
        name: 'Clock Events',
        importance: Notifications.AndroidImportance.HIGH,
        description: 'Clock in/out confirmations',
      });

      await Notifications.setNotificationChannelAsync('overtime', {
        name: 'Overtime Alerts',
        importance: Notifications.AndroidImportance.HIGH,
        description: 'Alerts when approaching overtime thresholds',
      });
    }
  }

  /**
   * Register the device push token with the Laravel backend.
   */
  private async registerTokenWithServer(token: string): Promise<void> {
    try {
      await apiClient.post('/device-tokens', {
        token,
        platform: Platform.OS,
        device_name: Device.deviceName ?? 'Unknown',
      });
      console.log('[Notifications] Token registered with server.');
    } catch (err) {
      console.error('[Notifications] Failed to register token:', err);
    }
  }

  /**
   * Listen for incoming notifications (foreground and background).
   * Returns cleanup function.
   */
  setupListeners(
    onNotification?: (notification: Notifications.Notification) => void,
    onNotificationResponse?: (response: Notifications.NotificationResponse) => void,
  ): () => void {
    const notificationSub = Notifications.addNotificationReceivedListener(
      (notification) => {
        console.log('[Notifications] Received:', notification.request.content.title);
        onNotification?.(notification);
      },
    );

    const responseSub = Notifications.addNotificationResponseReceivedListener(
      (response) => {
        const data = response.notification.request.content.data;
        console.log('[Notifications] Response:', data);
        onNotificationResponse?.(response);
      },
    );

    return () => {
      notificationSub.remove();
      responseSub.remove();
    };
  }

  /**
   * Show a local notification (for clock in/out confirmations).
   */
  async showLocalNotification(
    title: string,
    body: string,
    channelId: string = 'default',
  ): Promise<void> {
    await Notifications.scheduleNotificationAsync({
      content: {
        title,
        body,
        sound: 'default',
        ...(Platform.OS === 'android' ? { channelId } : {}),
      },
      trigger: null, // immediate
    });
  }

  /**
   * Show clock-in confirmation notification.
   */
  async notifyClockIn(jobName?: string): Promise<void> {
    const body = jobName
      ? `You clocked in at ${jobName}.`
      : 'You have been clocked in.';
    await this.showLocalNotification('Clocked In', body, 'clock');
  }

  /**
   * Show clock-out confirmation notification.
   */
  async notifyClockOut(totalHours: number): Promise<void> {
    const hours = totalHours.toFixed(1);
    await this.showLocalNotification(
      'Clocked Out',
      `You have been clocked out. Total: ${hours} hours today.`,
      'clock',
    );
  }

  /**
   * Show overtime approaching notification.
   */
  async notifyOvertimeApproaching(currentHours: number, threshold: number): Promise<void> {
    await this.showLocalNotification(
      'Overtime Alert',
      `You have worked ${currentHours.toFixed(1)} hours this week. Overtime begins at ${threshold} hours.`,
      'overtime',
    );
  }

  /**
   * Get current push token.
   */
  getToken(): string | null {
    return this.expoPushToken;
  }
}

export const notificationService = new NotificationService();
```

- [ ] **Step 2: Manual verification**

Build and run on a physical device:
- Verify notification permission prompt appears
- Verify push token is logged to console
- Verify Android notification channels are created
- Trigger a local notification (via clock-in) and verify it appears

- [ ] **Step 3: Commit**

```bash
git add mobile/src/services/notifications.ts
git commit -m "feat(mobile): add push notification service with FCM support and local notifications"
```

---

## Task 13: Settings & Profile Screen

**Files:**
- Modify: `mobile/src/screens/settings/SettingsScreen.tsx`

- [ ] **Step 1: Build the SettingsScreen**

```tsx
// mobile/src/screens/settings/SettingsScreen.tsx
import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  Switch,
} from 'react-native';
import { useAuth } from '@/hooks/useAuth';
import { useSyncStore } from '@/store/syncStore';
import { syncManager } from '@/services/sync';
import { geofenceService } from '@/services/geofence';
import { database } from '@/database';

export function SettingsScreen() {
  const { user, tenant, logout } = useAuth();
  const { lastSyncedAt, pendingCount, isOnline, isSyncing, triggerSync } = useSyncStore();
  const [geofenceCount, setGeofenceCount] = useState(0);
  const [notificationsEnabled, setNotificationsEnabled] = useState(true);

  useEffect(() => {
    loadGeofenceCount();
  }, []);

  const loadGeofenceCount = async () => {
    try {
      const state = await geofenceService.getState();
      // The Transistor library doesn't expose geofence count directly on state,
      // so we count from our database
      const { geofencesCollection } = require('@/database');
      const { Q } = require('@nozbe/watermelondb');
      const count = await geofencesCollection
        .query(Q.where('is_active', true))
        .fetchCount();
      setGeofenceCount(count);
    } catch {
      setGeofenceCount(0);
    }
  };

  const handleLogout = () => {
    Alert.alert(
      'Sign Out',
      pendingCount > 0
        ? `You have ${pendingCount} unsynced entries. Signing out will not delete them, but they won't sync until you sign back in.\n\nSign out anyway?`
        : 'Are you sure you want to sign out?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Sign Out',
          style: 'destructive',
          onPress: async () => {
            await geofenceService.stopTracking();
            await syncManager.clearSyncData();
            await logout();
          },
        },
      ],
    );
  };

  const handleForceSync = async () => {
    if (isSyncing) return;
    await triggerSync();
    Alert.alert('Sync', 'Sync triggered.');
  };

  const handleClearLocalData = () => {
    Alert.alert(
      'Clear Local Data',
      'This will delete all locally cached data (geofences, teams, jobs). Your time entries will NOT be deleted. Data will re-download on next sync.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear',
          style: 'destructive',
          onPress: async () => {
            try {
              await database.write(async () => {
                const { geofencesCollection, teamsCollection, jobsCollection } = require('@/database');
                const allGeofences = await geofencesCollection.query().fetch();
                const allTeams = await teamsCollection.query().fetch();
                const allJobs = await jobsCollection.query().fetch();

                for (const item of [...allGeofences, ...allTeams, ...allJobs]) {
                  await item.markAsDeleted();
                }
              });
              Alert.alert('Done', 'Local cache cleared. Pull down to sync fresh data.');
            } catch (err) {
              Alert.alert('Error', 'Failed to clear local data.');
            }
          },
        },
      ],
    );
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Profile section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Profile</Text>
        <View style={styles.profileCard}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {user?.name?.charAt(0).toUpperCase() ?? '?'}
            </Text>
          </View>
          <View style={styles.profileInfo}>
            <Text style={styles.profileName}>{user?.name ?? 'Unknown'}</Text>
            <Text style={styles.profileEmail}>{user?.email ?? ''}</Text>
            <Text style={styles.profileRole}>{user?.role?.replace('_', ' ') ?? ''}</Text>
          </View>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Company</Text>
          <Text style={styles.infoValue}>{tenant?.name ?? '--'}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Plan</Text>
          <Text style={styles.infoValue}>{tenant?.plan ?? '--'}</Text>
        </View>
      </View>

      {/* Sync status section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Sync Status</Text>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Connection</Text>
          <View style={styles.statusRow}>
            <View style={[styles.statusDot, { backgroundColor: isOnline ? '#16a34a' : '#f59e0b' }]} />
            <Text style={styles.infoValue}>{isOnline ? 'Online' : 'Offline'}</Text>
          </View>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Last Synced</Text>
          <Text style={styles.infoValue}>
            {lastSyncedAt
              ? new Date(lastSyncedAt).toLocaleString()
              : 'Never'}
          </Text>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Pending Items</Text>
          <Text style={[styles.infoValue, pendingCount > 0 ? styles.pendingWarning : undefined]}>
            {pendingCount}
          </Text>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Active Geofences</Text>
          <Text style={styles.infoValue}>{geofenceCount}</Text>
        </View>

        <TouchableOpacity
          style={[styles.actionButton, styles.syncButton]}
          onPress={handleForceSync}
          disabled={isSyncing || !isOnline}
        >
          <Text style={styles.actionButtonText}>
            {isSyncing ? 'Syncing...' : 'Sync Now'}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Preferences section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Preferences</Text>

        <View style={styles.switchRow}>
          <View>
            <Text style={styles.switchLabel}>Push Notifications</Text>
            <Text style={styles.switchDesc}>Clock confirmations, overtime alerts</Text>
          </View>
          <Switch
            value={notificationsEnabled}
            onValueChange={setNotificationsEnabled}
            trackColor={{ true: '#1a56db' }}
          />
        </View>
      </View>

      {/* Danger zone */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Advanced</Text>

        <TouchableOpacity
          style={[styles.actionButton, styles.clearButton]}
          onPress={handleClearLocalData}
        >
          <Text style={[styles.actionButtonText, styles.clearButtonText]}>
            Clear Local Cache
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, styles.logoutButton]}
          onPress={handleLogout}
        >
          <Text style={[styles.actionButtonText, styles.logoutButtonText]}>
            Sign Out
          </Text>
        </TouchableOpacity>
      </View>

      {/* App version */}
      <Text style={styles.version}>GeoTime v1.0.0</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    padding: 20,
    paddingBottom: 60,
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 12,
  },
  profileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#1a56db',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  avatarText: {
    color: '#ffffff',
    fontSize: 20,
    fontWeight: '700',
  },
  profileInfo: {
    flex: 1,
  },
  profileName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  profileEmail: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  profileRole: {
    fontSize: 12,
    color: '#1a56db',
    fontWeight: '600',
    textTransform: 'capitalize',
    marginTop: 2,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 14,
    color: '#374151',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    textTransform: 'capitalize',
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  pendingWarning: {
    color: '#f59e0b',
  },
  actionButton: {
    borderRadius: 8,
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 8,
  },
  actionButtonText: {
    fontSize: 14,
    fontWeight: '600',
  },
  syncButton: {
    backgroundColor: '#1a56db',
  },
  clearButton: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  clearButtonText: {
    color: '#374151',
  },
  logoutButton: {
    backgroundColor: '#fef2f2',
    borderWidth: 1,
    borderColor: '#fca5a5',
  },
  logoutButtonText: {
    color: '#dc2626',
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  switchLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  switchDesc: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  version: {
    textAlign: 'center',
    fontSize: 12,
    color: '#9ca3af',
    marginTop: 16,
  },
});
```

- [ ] **Step 2: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add mobile/src/screens/settings/
git commit -m "feat(mobile): add settings screen with profile, sync status, and preferences"
```

---

## Task 14: Wire Up App Initialization

**Files:**
- Modify: `mobile/App.tsx`

- [ ] **Step 1: Update App.tsx with full initialization flow**

```tsx
// mobile/App.tsx
import React, { useEffect, useState } from 'react';
import { ActivityIndicator, View, StyleSheet, AppState, AppStateStatus } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { NavigationContainer } from '@react-navigation/native';
import { DatabaseProvider } from '@nozbe/watermelondb/react';

import { database } from '@/database';
import { RootNavigator } from '@/navigation/RootNavigator';
import { useAuthStore } from '@/store/authStore';
import { geofenceService } from '@/services/geofence';
import { notificationService } from '@/services/notifications';
import { syncManager } from '@/services/sync';
import { useSyncStore } from '@/store/syncStore';
import { useClockStore } from '@/store/clockStore';

export default function App() {
  const [isReady, setIsReady] = useState(false);
  const restoreAuth = useAuthStore((s) => s.restoreAuth);
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  // Initialize app
  useEffect(() => {
    const init = async () => {
      await restoreAuth();
      setIsReady(true);
    };
    init();
  }, []);

  // Initialize services after authentication
  useEffect(() => {
    if (!isAuthenticated) return;

    const initServices = async () => {
      try {
        // Initialize geofence tracking
        await geofenceService.initialize();
        await geofenceService.registerGeofences();

        // Initialize push notifications
        await notificationService.initialize();

        // Load current clock status
        await useClockStore.getState().loadCurrentStatus();

        // Trigger initial sync
        await useSyncStore.getState().triggerSync();
      } catch (err) {
        console.error('[App] Service initialization error:', err);
      }
    };

    initServices();

    // Set up notification listeners
    const cleanupNotifications = notificationService.setupListeners(
      (notification) => {
        // Handle foreground notification
        console.log('[App] Foreground notification:', notification.request.content.title);
      },
      (response) => {
        // Handle notification tap — navigate based on data
        const data = response.notification.request.content.data;
        console.log('[App] Notification tapped:', data);
      },
    );

    return () => {
      cleanupNotifications();
    };
  }, [isAuthenticated]);

  if (!isReady) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color="#1a56db" />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <DatabaseProvider database={database}>
        <NavigationContainer>
          <RootNavigator />
        </NavigationContainer>
      </DatabaseProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  },
});
```

- [ ] **Step 2: Verify TypeScript compilation**

```bash
cd mobile
npx tsc --noEmit
```

Expected: No TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add mobile/App.tsx
git commit -m "feat(mobile): wire up app initialization with geofence, notification, and sync services"
```

---

## Task 15: Development Build & Manual Verification

- [ ] **Step 1: Build development client for iOS**

```bash
cd mobile
npx expo prebuild --clean
npx expo run:ios
```

Expected: App builds and launches on iOS simulator or device.

- [ ] **Step 2: Build development client for Android**

```bash
cd mobile
npx expo run:android
```

Expected: App builds and launches on Android emulator or device.

- [ ] **Step 3: Manual verification checklist**

Verify each feature on a physical device:

**Auth Flow:**
- [ ] App shows login screen on first launch
- [ ] Login with valid credentials shows dashboard
- [ ] Invalid credentials show error message
- [ ] Kill app and relaunch — auto-login works
- [ ] Logout clears session and returns to login

**Dashboard:**
- [ ] Shows user name and company
- [ ] Shows sync indicator
- [ ] Shows today's hours (0h if no entries)
- [ ] Shows weekly progress bar
- [ ] Pull-to-refresh triggers sync

**Clock In/Out:**
- [ ] Large green "CLOCK IN" button appears when off duty
- [ ] Tapping creates time entry with GPS coordinates
- [ ] Status changes to "Clocked In" with elapsed timer
- [ ] Button turns red "CLOCK OUT"
- [ ] Clock out confirmation dialog appears
- [ ] After clock out, status returns to off duty

**Breaks:**
- [ ] "Not Clocked In" shown when off duty
- [ ] Two break options appear when clocked in (Paid Rest, Meal Break)
- [ ] Break timer counts up in real-time
- [ ] FLSA warning appears if ending meal break < 30 minutes
- [ ] Completed breaks appear in history

**Timesheet:**
- [ ] Current week's entries displayed
- [ ] Week navigation (previous/next) works
- [ ] Weekly total shown correctly
- [ ] Empty state when no entries

**Settings:**
- [ ] Profile info displayed correctly
- [ ] Sync status (online/offline, last synced, pending count)
- [ ] Sign out with confirmation
- [ ] Pending count warning on sign out

**Offline Mode:**
- [ ] Enable airplane mode
- [ ] Clock in works (GPS uses satellite, no data needed)
- [ ] Break start/end works
- [ ] Clock out works
- [ ] Entries stored locally (visible in timesheet)
- [ ] Disable airplane mode — entries sync automatically

**Background Geolocation:**
- [ ] App requests "Always Allow" location permission
- [ ] After granting, geofences register (check console logs)
- [ ] Background notification visible on Android

- [ ] **Step 4: Run all tests**

```bash
cd mobile
npx jest --coverage
```

Expected: All unit and component tests pass.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore(mobile): final verification and cleanup for Plan 4"
```

- [ ] **Step 6: Push**

```bash
git push origin main
```
