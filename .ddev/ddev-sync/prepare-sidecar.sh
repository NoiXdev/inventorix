#!/usr/bin/env bash
#ddev-generated
# Runs on the host before every `ddev start` / `ddev restart`, from the
# pre-start hook in .ddev/config.ddev-sync.yaml.
#
# It regenerates the facts about the sidecar that belong to the machine it
# runs on rather than to the project: the group that owns the docker socket,
# the developer's home directory on the host, and a passwd entry for their
# uid. The first and the last used to be worked out once at install time, but
# a project's .ddev/ directory is committed and shared, so a teammate would
# inherit values discovered on somebody else's machine and never run the
# discovery themselves. Doing it at every start costs one short-lived
# container and cannot go stale.
set -eu

approot="${DDEV_APPROOT:?this script is meant to run as a DDEV hook}"
# Two files, and the difference matters. `.ddev/.env.local` is where the two
# machine-specific lines below are written, because DDEV's own generated
# `.ddev/.gitignore` covers `/.env.local` - so they stay out of the repository
# by construction instead of by advice. That DDEV substitutes compose
# variables from it at all was measured, not assumed, on v1.25.4: a value set
# only in `.env.local` reaches `ddev debug compose-config`, and when both
# files set the same key, `.env.local` wins.
#
# `.ddev/.env` is still read, because that is where the user's own
# DDEV_SYNC_IMAGE and DDEV_SYNC_VERSION lines live, and where a project
# installed before this change still has the two machine lines. It is never
# written any more, except to take those two lines back out of it.
env_local_file="${approot}/.ddev/.env.local"
env_file="${approot}/.ddev/.env"
state_dir="${approot}/.ddev/ddev-sync"

# Every temporary file is registered here so that a failure removes it. The
# empty first element keeps `"${tmp_files[@]}"` valid under `set -u` in the
# bash 3.2 that macOS ships.
tmp_files=("")
cleanup() { rm -f "${tmp_files[@]}"; }
trap cleanup EXIT

# Creates a temporary file next to $1 and leaves its name in $tmp. It does not
# print it: a command substitution would run in a subshell and lose the
# registration above.
new_tmp() {
  tmp="$(mktemp "$1.XXXXXX")" || exit 1
  tmp_files+=("${tmp}")
}

# announce_pull IMAGE MESSAGE: say a pull is coming, but only when one is.
#
# Both calls below read something out of a container, and a pull's own progress
# goes into the captured error file - so on the first use the developer would
# otherwise sit through a multi-hundred-megabyte download with nothing on
# screen. Printed unconditionally, which is how these started, they were two
# lines of noise on every single start for a pull that happens once. `docker
# image inspect` asks the local daemon whether the image is already there and
# nothing else; measured at 30-50 ms, against the 0.3-0.4 s each of the reads
# below costs anyway.
announce_pull() {
  docker image inspect "$1" >/dev/null 2>&1 || echo "ddev-sync: $2" >&2
}

# fail MESSAGE [FILE [HINT [URL]]]: the message, FILE's content indented under it,
# a hint saying what to do about it, and the docs entry that explains it as the
# last line. The message is printed before DDEV
# carries on: a failed pre-start hook does not abort the start (that is
# `fail_on_hook_fail`, which is the user's to set), so DDEV's own error for the
# same problem may follow it as the last line.
fail() {
  echo "ddev-sync: $1" >&2
  if [ -n "${2:-}" ] && [ -s "$2" ]; then sed 's/^/ddev-sync:   /' "$2" >&2; fi
  if [ -n "${3:-}" ]; then echo "ddev-sync: $3" >&2; fi
  if [ -n "${4:-}" ]; then echo "ddev-sync: More: $4" >&2; fi
  exit 1
}

new_tmp "${TMPDIR:-/tmp}/ddev-sync-err"
err_file="${tmp}"

# --- The docker socket's group ------------------------------------------------
# Read from inside a container, not from the host: what matters is the group
# the socket has where the sidecar will see it. On a typical Linux that is the
# host's `docker` group, but under Docker Desktop the host path is a symlink
# whose own group (daemon) means nothing - the daemon runs in a VM where the
# same socket belongs to root, gid 0, and only that gid grants access. A
# host-side `stat` was verified to answer 1 there, which fails with a
# permission error that reads like a bug in the tool.
#
# `--mount` rather than `-v`: with `-v` a missing source is silently created as
# an empty directory, and `stat` would then report a plausible but bogus gid.
# ddev/ddev-utilities is the image DDEV itself uses for one-off commands, so it
# is normally present already.
announce_pull ddev/ddev-utilities \
  "pulling ddev/ddev-utilities to read the docker socket's group; this happens once..."
gid="$(docker run --rm \
  --mount type=bind,src=/var/run/docker.sock,dst=/var/run/docker.sock \
  ddev/ddev-utilities stat -c '%g' /var/run/docker.sock 2>"${err_file}")" \
  || fail "could not read the group of /var/run/docker.sock - is Docker running? Docker said:" "${err_file}"
case "${gid}" in
  '' | *[!0-9]*) fail "unexpected group id '${gid}' for /var/run/docker.sock" ;;
esac

# --- The developer's home directory on the host ------------------------------
# The engine reads its global configuration layer from
# ~/.ddev/ddev-sync/config.yaml, which is where targets belong so that
# production hosts and users are not committed with the project. Inside the
# sidecar no home directory of the developer's exists, so the compose file
# mounts that one directory in and commands/sync/sync points the engine at it;
# this is where its location on the host comes from. It is a per-machine fact
# like the gid above, because .ddev/ is shared between machines and home
# directories are not.
host_home="${HOME:-}"
[ -n "${host_home}" ] || fail "HOME is not set, so the directory that holds your sync targets (~/.ddev/ddev-sync) cannot be located." "" \
  "This script runs as a DDEV exec-host hook, which inherits your shell's environment; a HOME that is missing there is worth fixing on its own."

# Created here, as the developer, before the service can start: Docker creates
# a missing bind-mount source itself, and a daemon running as root creates it
# owned by root - a directory in the developer's own home that they could then
# not write their targets into.
mkdir -p "${host_home}/.ddev/ddev-sync"

# Replace the lines rather than append, and write through the existing file
# (`cat >`, not `mv`) so that its mode, and a symlink in its place, survive.
#
# `grep -v` exits 1 when it matched every line, which here means an .env that
# holds nothing but these lines, so that is success. Exit 2 is a real failure -
# an .env that cannot be read - and without a branch of its own `set -e` would
# kill the script with no message of ours at all.
# Strip the two lines from whichever file holds them, and keep whatever else
# that file had. `grep -v` exits 1 when it matched every line, which here
# means a file holding nothing but these two, so that is success. Exit 2 is a
# real failure - a file that cannot be read - and without a branch of its own
# `set -e` would kill the script with no message of ours at all.
strip_machine_lines() {
  [ ! -f "$1" ] \
    || grep -v -e '^DDEV_SYNC_DOCKER_GID=' -e '^DDEV_SYNC_HOST_HOME=' "$1" \
    || [ $? -eq 1 ] \
    || fail "could not read $1, so the lines ddev-sync keeps there (DDEV_SYNC_DOCKER_GID, DDEV_SYNC_HOST_HOME) cannot be rewritten." "" \
      "Check that the file is readable and is a regular file or a symlink to one."
}

# Write through the existing file (`cat >`, not `mv`) so that its mode, and a
# symlink in its place, survive.
new_tmp "${env_local_file}"
env_tmp="${tmp}"
strip_machine_lines "${env_local_file}" > "${env_tmp}"
printf 'DDEV_SYNC_DOCKER_GID=%s\n' "${gid}" >> "${env_tmp}"
printf 'DDEV_SYNC_HOST_HOME=%s\n' "${host_home}" >> "${env_tmp}"
cmp -s "${env_tmp}" "${env_local_file}" 2>/dev/null || cat "${env_tmp}" > "${env_local_file}"

# A project that was started before these lines moved still has them in
# .ddev/.env, where they are not ignored. Taking them out is the whole point
# of the move, so it happens here rather than being left to the user - and
# only when they are actually there, so a project that never had them keeps
# its .env untouched, mode and symlink included. `.env.local` wins the
# substitution either way, so a stale pair would be harmless and invisible,
# which is exactly why nobody would ever remove it.
if [ -f "${env_file}" ] && grep -q -e '^DDEV_SYNC_DOCKER_GID=' -e '^DDEV_SYNC_HOST_HOME=' "${env_file}"; then
  new_tmp "${env_file}"
  legacy_tmp="${tmp}"
  strip_machine_lines "${env_file}" > "${legacy_tmp}"
  cat "${legacy_tmp}" > "${env_file}"
  echo "ddev-sync: moved DDEV_SYNC_DOCKER_GID and DDEV_SYNC_HOST_HOME from .ddev/.env to .ddev/.env.local, which git ignores." >&2
fi

# --- DDEV's ssh agent ----------------------------------------------------------
# The sidecar authenticates to remote hosts through the agent DDEV forwards,
# and the compose file mounts that agent's socket volume. DDEV declares the
# volume only while the agent is not omitted (`omit_containers` in the global
# config, the project's, or any config.*.yaml), and a service that mounts an
# undeclared volume makes DDEV refuse the whole project with "service ... refers
# to undefined volume ddev-ssh-agent_socket_dir: invalid compose project". That
# reads like a bug in the add-on and stops every `ddev start`, so it is caught
# here, where the cause can be named.
#
# The question is put to DDEV itself because the omission can come from several
# places and only DDEV merges them. The probe needs DDEV_SYNC_DOCKER_GID and
# DDEV_SYNC_HOST_HOME, which the sections above have just written, or the
# compose file would fail on one of those instead - which is why this probe
# comes after them and not before. A failure that does not mention the agent's
# volume is ignored on purpose: it is not ours to explain, and DDEV will report
# it on its own a moment later.
#
# The volume's absence is no substitute: before the first start the agent has
# not created it yet even when nothing is omitted. Declaring the volume
# `external` in the compose file does not help either: with the agent omitted
# it would be accepted and then either fail at `up` on a machine that never ran
# the agent, or start against a stale socket on one that did, and the second is
# a failure that surfaces only in `ddev sync doctor`.
if ! compose_out="$(cd "${approot}" && ddev debug compose-config 2>&1)"; then
  case "${compose_out}" in
    *ddev-ssh-agent_socket_dir*)
      fail "DDEV's ssh agent is switched off, and ddev-sync needs it: the sidecar authenticates to remote hosts through the agent DDEV forwards, and without it there is nothing to authenticate with." "" \
        "Remove ddev-ssh-agent from omit_containers wherever it is listed: the global config (~/.ddev/global_config.yaml), .ddev/config.yaml, or a .ddev/config.*.yaml file. Edit the list rather than clearing it: 'ddev config global --omit-containers=' would also switch back on anything else omitted globally, such as ddev-router. Then start again." \
        "https://docs.noix.dev/ddev-sync/reference/troubleshooting/#ssh-agent-switched-off"
      ;;
  esac
fi

# --- The sidecar's user: a passwd entry and a home directory -----------------
# The sidecar runs as the developer's uid so that files it writes belong to
# them, but the image only knows its own user. OpenSSH calls getpwuid() before
# it reads any option and aborts with "No user exists for uid" when there is
# no entry. musl reads /etc/passwd directly, so mounting a file that has the
# image's own entries plus one for this uid is all it takes.
#
# The image's file is the base, because replacing it with a single line would
# drop root and nobody. The image reference is resolved the way compose
# resolves it, shell environment before .ddev/.env, quotes removed; its
# defaults are repeated from docker-compose.ddev-sync.yaml rather than shared,
# because compose substitutes them itself and the only way to ask it what it
# would use is to run it, which needs the very values this script produces.
# Keep the two in step.
setting() {
  local value candidate
  eval "value=\"\${$1:-}\""
  # `.env.local` before `.env`, because that is the order compose resolves
  # them in - measured on DDEV v1.25.4, where a key set in both is taken from
  # `.env.local`. Reading them the other way round would resolve a different
  # image here than the compose file uses, which is the drift the comment
  # above warns about.
  for candidate in "${env_local_file}" "${env_file}"; do
    if [ -z "${value}" ] && [ -f "${candidate}" ]; then
      value="$(grep "^$1=" "${candidate}" | tail -n 1 | cut -d= -f2-)"
    fi
  done
  value="${value:-$2}"
  # Compose strips one pair of surrounding quotes, so that is done here too.
  case "${value}" in
    \"*\" | \'*\') value="${value:1:${#value}-2}" ;;
  esac
  printf '%s' "${value}"
}
image="$(setting DDEV_SYNC_IMAGE noixdev/ddev-sync):$(setting DDEV_SYNC_VERSION 0.1.0-rc.7)"

# Both are bind-mounted by the compose file. If the service ever starts
# without this script having run, Docker creates a missing source as an empty
# directory owned by root - for the passwd file that would make the `mv` below
# move the file into the directory and still exit 0.
passwd_file="${state_dir}/passwd"
home_dir="${state_dir}/home"
if [ -d "${passwd_file}" ]; then
  fail "${passwd_file} is a directory, which Docker creates when the service starts before this script has run. Remove it (sudo may be needed) and start again."
fi
if [ -e "${home_dir}" ] && { [ ! -d "${home_dir}" ] || [ ! -O "${home_dir}" ]; }; then
  fail "${home_dir} is not a directory of yours, which Docker creates when the service starts before this script has run. Remove it (sudo may be needed) and start again."
fi

uid="${DDEV_UID:?DDEV did not export it to this hook, so the passwd entry the sidecar runs with cannot be written}"
new_tmp "${state_dir}/passwd"
passwd_tmp="${tmp}"
new_tmp "${state_dir}/passwd"
base_tmp="${tmp}"
# Pulling can take a while and the pull's own progress goes into the captured
# error file, so say what is happening before it starts - and only then.
announce_pull "${image}" \
  "pulling ${image} to read its user database; this happens once per image..."
docker run --rm --entrypoint cat "${image}" /etc/passwd > "${passwd_tmp}" 2>"${err_file}" \
  || fail "could not read /etc/passwd from the image ${image}. Docker said:" "${err_file}" \
    "The project cannot start without it. Check that the image name and version are right (DDEV_SYNC_IMAGE and DDEV_SYNC_VERSION in .ddev/.env.local or .ddev/.env override the add-on's own defaults) and that Docker can reach the registry. To run against a local build instead: docker build -t ddev-sync:dev . from the ddev-sync checkout, and put DDEV_SYNC_IMAGE=ddev-sync and DDEV_SYNC_VERSION=dev in .ddev/.env.local or .ddev/.env." \
    "https://docs.noix.dev/ddev-sync/reference/troubleshooting/#sidecar-image-not-found"
if [ "${uid}" != 0 ]; then
  # A line for this uid already in the image (1000 is common on Linux) would
  # win over the new one, because the first match is returned.
  awk -F: -v uid="${uid}" '$3 != uid' "${passwd_tmp}" > "${base_tmp}"
  { cat "${base_tmp}"; echo "ddev:x:${uid}:${DDEV_GID:?DDEV did not export it to this hook, so the passwd entry the sidecar runs with cannot be written}:ddev:/home/ddev:/bin/sh"; } > "${passwd_tmp}"
fi
chmod 644 "${passwd_tmp}"
mv "${passwd_tmp}" "${passwd_file}"

# The home directory the entry names. It is a bind mount rather than a tmpfs
# because ssh keeps known_hosts in it: a tmpfs would be empty again after every
# `ddev stop`, and every sync would ask the developer to trust the host anew.
# Created here, as the developer, so that it belongs to the uid the sidecar
# runs as.
mkdir -p "${home_dir}"
chmod 700 "${home_dir}"
