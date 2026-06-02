#!/usr/bin/env bash
set -euo pipefail

latest=$(git tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -1)

if [[ -n "${1:-}" ]]; then
    version="$1"
else
    if [[ -z "$latest" ]]; then
        echo "No existing tags found. Please provide a version." >&2
        exit 1
    fi
    IFS='.' read -r major minor patch <<< "${latest#v}"
    version="${major}.${minor}.$((patch + 1))"
fi

tag="v${version}"

if git tag | grep -qx "$tag"; then
    echo "Tag $tag already exists." >&2
    exit 1
fi

echo "Tagging $tag"
git tag -a "$tag" -m "release $tag"
git push origin "$tag"
