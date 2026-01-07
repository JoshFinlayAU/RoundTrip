import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'export',
  basePath: '/roundtrip',
  trailingSlash: true,
  reactCompiler: true,
};

export default nextConfig;
