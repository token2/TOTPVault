{{/*
Expand the name of the chart.
*/}}
{{- define "totpvault.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name (63 char limit per DNS naming spec).
If the release name contains the chart name it is used as the full name.
*/}}
{{- define "totpvault.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Chart name and version for the helm.sh/chart label.
*/}}
{{- define "totpvault.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels.
*/}}
{{- define "totpvault.labels" -}}
helm.sh/chart: {{ include "totpvault.chart" . }}
{{ include "totpvault.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels shared by all components.
*/}}
{{- define "totpvault.selectorLabels" -}}
app.kubernetes.io/name: {{ include "totpvault.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Selector labels for the application workload. The component label keeps the
app Service/Deployment selectors from also matching the MariaDB pods.
*/}}
{{- define "totpvault.app.selectorLabels" -}}
{{ include "totpvault.selectorLabels" . }}
app.kubernetes.io/component: app
{{- end }}

{{/*
MariaDB object name and selector labels.
*/}}
{{- define "totpvault.mariadb.fullname" -}}
{{- printf "%s-mariadb" (include "totpvault.fullname" .) | trunc 63 | trimSuffix "-" }}
{{- end }}

{{- define "totpvault.mariadb.selectorLabels" -}}
{{ include "totpvault.selectorLabels" . }}
app.kubernetes.io/component: mariadb
{{- end }}

{{/*
Service account name.
*/}}
{{- define "totpvault.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "totpvault.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Name of the Secret holding the sensitive environment variables.
*/}}
{{- define "totpvault.secretName" -}}
{{- default (include "totpvault.fullname" .) .Values.secrets.existingSecret }}
{{- end }}

{{/*
Database connection details: bundled MariaDB service when mariadb.enabled,
otherwise the externalDatabase values (host is then required).
*/}}
{{- define "totpvault.databaseHost" -}}
{{- if .Values.mariadb.enabled }}
{{- include "totpvault.mariadb.fullname" . }}
{{- else }}
{{- required "externalDatabase.host is required when mariadb.enabled is false" .Values.externalDatabase.host }}
{{- end }}
{{- end }}

{{- define "totpvault.databasePort" -}}
{{- if .Values.mariadb.enabled }}3306{{- else }}{{ .Values.externalDatabase.port }}{{- end }}
{{- end }}

{{- define "totpvault.databaseName" -}}
{{- if .Values.mariadb.enabled }}{{ .Values.mariadb.auth.database }}{{- else }}{{ .Values.externalDatabase.database }}{{- end }}
{{- end }}

{{- define "totpvault.databaseUser" -}}
{{- if .Values.mariadb.enabled }}{{ .Values.mariadb.auth.username }}{{- else }}{{ .Values.externalDatabase.username }}{{- end }}
{{- end }}

{{/*
Public application URL: explicit app.url, else derived from the first ingress
host (https when TLS is configured), else a localhost default for
port-forward access.
*/}}
{{- define "totpvault.appUrl" -}}
{{- if .Values.app.url }}
{{- .Values.app.url | trimSuffix "/" }}
{{- else if and .Values.ingress.enabled .Values.ingress.hosts }}
{{- $host := (first .Values.ingress.hosts).host }}
{{- $scheme := ternary "https" "http" (gt (len .Values.ingress.tls) 0) }}
{{- printf "%s://%s" $scheme $host }}
{{- else }}
{{- "http://localhost:8080" }}
{{- end }}
{{- end }}
