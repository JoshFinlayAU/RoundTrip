import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'export',
  basePath: '/roundtrip',
  reactCompiler: true,
};

export default nextConfig;
