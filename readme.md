Last login: Wed May 28 10:15:33 on ttys000  
  
joeri@MacBook ~ % `cd /Users/joeri/Projects`  
joeri@MacBook Projects % `git clone https://github.com/bespired/burner.git` 
  
	Cloning into 'burner'...  
	remote: Enumerating objects: 43, done.  
	remote: Counting objects: 100% (43/43), done.  
	remote: Compressing objects: 100% (38/38), done.  
	remote: Total 43 (delta 2), reused 43 (delta 2), pack-reused 0 (from 0)  
	Receiving objects: 100% (43/43), 18.92 KiB | 6.31 MiB/s, done.  
	Resolving deltas: 100% (2/2), done.  
  
joeri@MacBook Projects % `cd burner`  
joeri@MacBook burner % `docker compose up -d` 
  
	Compose can now delegate builds to bake for better performance.  
	 To do so, set COMPOSE_BAKE=true.  
	[+] Building 0.1s (18/18) FINISHED  
	 => [apache internal] load build definition from apache.dockerfile 0.0s  
	 => => transferring dockerfile: 1.06kB  
	 => [apache internal] load metadata for docker.io/library/php:8.4-apache  
	 => [apache internal] load .dockerignore  
	 => [apache internal] load build context  
	 => [apache] exporting to image  
	 => exporting layers  
	 => writing image sha256:94fda92fe0d274c11ce177458a76303b879cfce0a0620df50855c3e671dcb573  
	 => naming to docker.io/library/burner-apache  
	 => [apache] resolving provenance for metadata file  
	[+] Running 4/4  
	 ✔ Network burner-docker.network  Created  
	 ✔ Container travel.mysql         Started  
	 ✔ Container travel.redis         Started  
	 ✔ Container travel.apache        Started  
  
joeri@MacBook burner % `cd planner/database`  
joeri@MacBook burner % `php install.php`

	Database created successfully.
	owners created.
	hashes created.
	holidays created.
	continents created.
	countries created.
	country-pivot created.
	informations created.
	Create index for handle on holidays.
	Create index for owner on holidays.
	Seeded continents table
	Seeded countries table
    
 
 ![](https://raw.githubusercontent.com/bespired/burner/refs/heads/main/docker/sequel-ace.png)